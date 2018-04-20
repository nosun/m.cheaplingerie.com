<?php
class Front_Model extends Bl_Model
{
  /**
   * @return Front_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 用于取特定的页面的 pv 信息，如首页
   * @param object $instance controller 实例
   * @param string $key pagevariable key 值
   * @param $return 是否返回 或者直接写入模板变量中
   */
  public function getPageVariableByKey(Bl_Controller $instance, $key, $return = false, $keyword = null)
  {
    $pageInstance = PageVariable_Model::getInstance();
    $pageInfo = $pageInstance->getPageVariableByKey($key);
    if ($key == 'search' && isset($keyword) && $keyword) {
      
      if(false != $pageInfo){
        foreach ($pageInfo as $k => $v) {
          if ($v) {
            preg_match_all('/\{keyword\}(.*?)/', $v, $regs);
            if (isset($regs[0]) && $regs[0]) {
              foreach ($regs[0] as $k2 => $v2) {
                //改变当前的描述为和keyword相关的形式。
                $pageInfo->$k = str_replace($regs[0][$k2], $keyword, $pageInfo->$k);
              }
            }
          }
        }
      }
    }
    $pageInfo = $pageInstance->replaceSiteVariables($pageInfo);
    if ($pageInfo) {
      if ($return) {
        return $pageInfo;
      } else {
        return $instance->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
      }
    }
  }

  /**
   * 根据  $pvid 取页面的 pv 信息
   * @param object $instance controller 实例
   * @param int $pvid 页面的pvid
   */
  public function getPageVariable(Bl_Controller $instance, $pvid, $type, $data, $return = false)
  {
    $pageInstance = PageVariable_Model::getInstance();
    $pageInfo = $pageInstance->selectPageVariables($pvid, $type, $data);
    if ($pageInfo) {
      if ($return) {
        return $pageInfo;
      } else {
        return $instance->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
      }
    }

  }


  /**
   * 取文章列表信息
   * @param array $filter
   * @param int $page
   * @param int $pageRows
   * @param boolean $getlower 是否获取下级分类的文章
   */
  public function getArticleList($filter, $page = null, $pageRows = null, $getlower = true)
  {
    $contentInstance = Content_Model::getInstance();
    return $contentInstance->getArticleList($filter, $page, $pageRows, $getlower);
  }

  /**
   * 获取文章详细信息
   * @param $aid 文章ID
   */
  public function getArticleInfo($aid)
  {
    $contentInstance = Content_Model::getInstance();
    return $contentInstance->getArticleInfo($aid);
  }

  /**
   * 获取一个商品的评论列表
   *@param $pid 商品ID
   *@param $filter 商品过滤条件
   *@param $page
   *@param $pageRows
   *@return array
   */
  public function getCommentsListByProductId($pid, $filter = array('status' => 1), $page=1, $pageRows = 10)
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->getCommentsListByProductId($pid, $filter, $page, $pageRows);
  }

  /**
   *
   * 取所有的分类信息
   */
  public function getTermsList($tree = true, $getProductNum = false)
  {
    $taxonomyInstance = Taxonomy_Model::getInstance();
    $vocabularyInfo = $taxonomyInstance->getVocabularyInfoByVid(Taxonomy_Model::TYPE_DIRECTORY);
    if ($vocabularyInfo) {
      $vid = $vocabularyInfo->vid;
      $termsList = $taxonomyInstance->getTermsList($vid, $tree, $getProductNum);
    }
    return $termsList ? $termsList : array();
  }

  /**
   *
   * 取所有的分类信息
   */
  public function getTagsList($getProductNum = false)
  {
    $taxonomyInstance = Taxonomy_Model::getInstance();
    $vocabularyInfo = $taxonomyInstance->getVocabularyInfoByVid(Taxonomy_Model::TYPE_TAG);
    if ($vocabularyInfo) {
      $vid = $vocabularyInfo->vid;
      $termsList = $taxonomyInstance->getTermsList($vid, true, $getProductNum);
    }
    return $termsList ? $termsList : array();
  }

  /**
   *
   * 取所有的品牌信息
   */
  public function getBrandsList($getProductNum = false)
  {
    $taxonomyInstance = Taxonomy_Model::getInstance();
    $vocabularyInfo = $taxonomyInstance->getVocabularyInfoByVid(Taxonomy_Model::TYPE_BRAND);
    if ($vocabularyInfo) {
      $vid = $vocabularyInfo->vid;
      $termsList = $taxonomyInstance->getTermsList($vid, true, $getProductNum);
    }
    return $termsList ? $termsList : array();
  }

  /**
   * 获取商品信息
   * @param array $filter
   * @param int $page
   * @param int $pageRows
   */
  public function getProductsList($filter = array(), $page = null, $pageRows = null)
  {
    $productInstance = Product_Model::getInstance();
    return $productInstance->getProductsList($filter, $page, $pageRows);
  }

/**
   * 取商品数量
   * @param array $filter
   */
  public function getProductsCount($filter = array())
  {
    $productInstance = Product_Model::getInstance();
    return $productInstance->getProductsCount($filter);
  }

  /**
   * 取特殊的分类的商品
   * @param string $op （_HOT 热卖商品，_REC 推荐商品）
   * @param int $page
   * @param int $pageRows
   */
  public function getProductsListBySpecial($filter = array(), $page = null, $pageRows = null, $ifrandom = true)
  {
    $productInstance = Product_Model::getInstance();
    $taxonomyInstance = Taxonomy_Model::getInstance();
    $productsList = $productInstance->getProductsListBySpecial($filter, $page, $pageRows);
    if (!$productsList && $ifrandom) {
      if (isset($filter['termname']) && $filter['termname']) {
        $termInfo = $taxonomyInstance->getTermInfoByName($filter['termname']);
      }
      if (isset($termInfo->tid) && $termInfo->tid) {
        $filter['orderby'] = 'rand()';
        $productsList = $productInstance->getProductsList($filter, $page, $pageRows);
        if ($productsList) {
          foreach ($productsList as $k => $v) {
            $productInstance->insertProductTerms($v->pid, $termInfo->tid);
          }
        }
      }
    }
    return isset($productsList) ? $productsList : array();
  }

  /**
   * 取特殊的分类的商品数量
   * @param array $filter
   */
  public function getProductsCountBySpecial($filter = array())
  {
    $productInstance = Product_Model::getInstance();
    return $productInstance->getProductsCountBySpecial($filter);
  }

  /**
   * 获取购物车商品数量
   */
  public function getCartCount()
  {
    $cartInstance = Cart_Model::getInstance();
    return $cartInstance->getCartCount();
  }

  /**
   * 获取购物车详情
   */
  public function getCartProductList()
  {
    $cartInstance = Cart_Model::getInstance();
    return $cartInstance->getCartProductList();
  }

  /**
   * 获取站点信息
   */
  public function getSiteInfo()
  {
    return Bl_Config::get('siteInfo', array());
  }

  /**
   * 获取公司联系方式
   */
  public function getContactWay()
  {
    return Bl_Config::get('contactWay', array());
  }

  /**
   * 获取用户留言信息列表
   * @param int $status 留言状态
   * @param int $page
   * @param int $pageRows
   */
  public function getWebsiteMessageList($page = 1, $pageRows = 10, $status = Comment_Model::MESSAGE_NOMAL)
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->getWebsiteMessageList($page, $pageRows, $status);
  }

  /**
   * 获取单个用户留言信息
   * @param $gbid
   */
  public function getWebsiteMessageInfo($gbid)
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->getWebsiteMessageInfo($gbid);
  }

  /**
   * 新增用户留言
   * @param array $post
   */
  public function insertWebsiteMessage($post)
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->insertWebsiteMessage($post);
  }

  /**
   * 获取促销活动信息
   */
  public function getPromotionsList()
  {
    $productInstance = Product_Model::getInstance();
    return $productInstance->getPromotionsList();
  }

  /**
   * 获取商品最大价、最小价格
   */
  public function getHighAndLowPrice($post)
  {
    $productInstance = Product_Model::getInstance();
    return $productInstance->getHighAndLowPrice($post);
  }

  /**
   * 新增订单评论
   * @param $oid 订单ID
   * @param $post 评论内容
   */
  public function insertOrdersComments($oid, $post)
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->insertOrdersComments($oid, $post);
  }

  /**
   * 获取订单评论列表
   * $param $post 过滤条件
   * $page 页数
   * $pageRows 每页显示多少条数
   */
  public function getOrdersCommentsList($oid, $filter = array('status' => 1), $page = 1, $pageRows = 20)
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->getOrdersCommentsList($oid, $filter, $page, $pageRows);
  }

  /**
   * 获取订单评论总数
   * $param $post 过滤条件
   * $page 页数
   * $pageRows 每页显示多少条数
   */
  public function getOrdersCommentsCount($oid, $filter = array())
  {
    $commentInstance = Comment_Model::getInstance();
    return $commentInstance->getOrdersCommentsCount($oid, $filter);
  }
}