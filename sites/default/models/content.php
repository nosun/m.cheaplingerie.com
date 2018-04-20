<?php
class Content_Model extends Bl_Model
{
  /**
   * @return Content_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   *
   * 获取文章列表
   * @param unknown_type $post
   * @param unknown_type $page
   * @param unknown_type $pageRows
   * @param boolean $getlower 是否获取下级分类的文章
   */
  public function getArticleList($post = array(), $page = 0, $pageRows = 0, $getlower = true)
  {
    global $db;
    if (isset($post['type_id']) && $post['type_id']) {
      $post['atid'] = $this->getArticleTypeInfoByTypeid($post['type_id']);
    }
    if ($getlower && isset($post['atid']) && $post['atid']) {
      $post['atid'] = $this->getArticleUnderType($post['atid']);
    }
    if (isset($post['tid']) && $post['tid']) {
      $taxonomyInstance = Taxonomy_Model::getInstance();
      $post['tid'] = $taxonomyInstance->getTermChildsToLinearArray($post['tid']);
    }
    $filter = array(
        'title LIKE' => isset($post['title']) && $post['title'] ? '%'.$post['title'].'%' : null,
        'atid IN' => isset($post['atid']) && $post['atid'] ? $post['atid'] : null,
        'tid IN' => isset($post['tid']) && $post['tid'] ? $post['tid'] : null,
        'status' => isset($post['status']) ? $post['status'] : null,
    );
    foreach ($filter as $key => $value) {
      if (isset($value) && $value !== '' && $value !== false) {
        $db->where($key, $value);
      }
    }
    if (isset($post['orderby']) && $post['orderby']) {
      $db->orderby('rand()');
    } else {
      $db->orderby('weight DESC, created DESC');
    }
    if($pageRows) {
      $db->limitPage($pageRows, $page);
    }
    $result = $db->get('articles');
    $list = $result->allWithKey('aid');
    foreach ($list as $k => $v) {
      $list[$k]->url = 'article/'.$list[$k]->path_alias.'.html';
    }
    return $list;
  }

  /**
   * 获取分类的下级分类id
   * @param unknown_type $atid
   */
  public function getArticleUnderType($atid)
  {
    global $db;
    $db->select('atid');
    $db->where('parent', $atid);
    $result = $db->get('articles_type');
    $arr = $result->column('atid');
    $arr[] = $atid;
    return $arr;
  }

  /**
   *
   * 获取文章列表的总数
   * @param unknown_type $post
   */
  public function getArticleCount($post = array())
  {
    global $db;
    if (isset($post['type_id']) && $post['type_id']) {
      $post['atid'] = $this->getArticleTypeInfoByTypeid($post['type_id']);
    }
    if (isset($post['atid']) && $post['atid']) {
      $post['atid'] = $this->getArticleUnderType($post['atid']);
    }
    if (isset($post['tid']) && $post['tid']) {
      $taxonomyInstance = Taxonomy_Model::getInstance();
      $post['tid'] = $taxonomyInstance->getTermChildsToLinearArray($post['tid']);
    }
    $db->select('COUNT(0)');
    $filter = array(
        'title LIKE' => isset($post['title']) && $post['title'] ? '%'.$post['title'].'%' : null,
        'atid IN' => isset($post['atid']) && !empty($post['atid']) ? $post['atid'] : null,
        'tid IN' => isset($post['tid']) && $post['tid'] ? $post['tid'] : null,
        'status' => isset($post['status']) ? $post['status'] : null,
    );
    foreach ($filter as $key => $value) {
      if (isset($value) && $value !== '' && $value !== false) {
        $db->where($key, $value);
      }
    }
    $result = $db->get('articles');
    return $result->one();
  }

  /**
   *
   * 获取文章详细信息，根据文章ID
   * @param unknown_type $aid
   */
  public function getArticleInfo($aid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$aid])) {
      $cacheId = 'article-' . $aid;
      if ($cache = cache::get($cacheId)) {
        $list[$aid] = $cache->data;
      } else {
        $db->where('aid', $aid);
        $result = $db->get('articles');
        $list[$aid] = $result->row();
        if ($list[$aid]) {
          $list[$aid]->url = 'article/'.$list[$aid]->path_alias.'.html';
        }
        cache::save($cacheId,  $list[$aid]);
      }
    }
    return $list[$aid];
  }

  /**
   *
   * 获取文章详细信息，根据别名
   * @param unknown_type $aid
   */
  public function getArticleInfoBypathAlias($pathAlias)
  {
    global $db;
    $db->select('aid');
    $db->where('path_alias', $pathAlias);
    $result = $db->get('articles');
    $aid = $result->one();
    if (isset($aid) && $aid) {
      return $this->getArticleInfo($aid);
    } else {
      return false;
    }
  }

 /**
   *
   * 获取文章别名信息
   * @param $pathAlias
   */
  public function getArticlePathAlias($pathAlias, $aid = 0)
  {
    $ArticleInfo = $this->getArticleInfoBypathAlias($pathAlias);
    if ($ArticleInfo && $aid != $ArticleInfo->aid) {
      return $pathAlias . '-' . $ArticleInfo->aid;
    } else {
      return $pathAlias;
    }
  }

/**
   * 获取文章分类别名信息
   * @param $pathAlias
   */
  public function getArticleTypePathAlias($pathAlias, $atid = 0)
  {
    $ArticleTypeInfo = $this->getArticleTypeInfoByPath($pathAlias);
    if ($ArticleTypeInfo && $atid != $ArticleTypeInfo->atid) {
      return $pathAlias . '-' . $ArticleTypeInfo->atid;
    } else {
      return $pathAlias;
    }
  }


  /**
   * 更新文章信息
   * @param int $aid 文章ID
   * @param array $post 更新值
   */
  public function updateArticle($aid, $post = array(), $isfilternull = 0)
  {
    global $db;
    $filter = array(
      'title' => isset($post['article_title']) ? $post['article_title'] : null,
      'path_alias' => isset($post['path_alias']) ? $post['path_alias'] : null,
      'atid' => isset($post['atid']) ? intval($post['atid']) : null,
      'tid' => isset($post['tid']) ? intval($post['tid']) : null,
      'status' => isset($post['status']) ? intval($post['status']) : null,
      'summary' => isset($post['summary']) ? $post['summary'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'weight' => isset($post['weight']) ? intval($post['weight']) : null,
      'pvid' => isset($post['pvid']) ? intval($post['pvid']) : null,
      'author' => isset($post['author']) ? $post['author'] : null,
      'source' => isset($post['source']) ? $post['source'] : null,
      'updated' => TIMESTAMP,
    );
    if($isfilternull) {
      $filter = array_filter($filter, "Common_Model::filterArray");
    }
    cache::remove('article-' . $aid);
    $db->update('articles', $filter, array('aid' => $aid));
    return $db->affected();
  }

  /**
   * 新增文章信息
   */
  public function insertArticle($post)
  {
    global $db;
    $filter = array(
      'title' => isset($post['article_title']) ? $post['article_title'] : null,
      'path_alias' => isset($post['path_alias']) ? $post['path_alias'] : null,
      'atid' => isset($post['atid']) ? intval($post['atid']) : null,
      'tid' => isset($post['tid']) ? intval($post['tid']) : null,
      'status' => isset($post['status']) ? intval($post['status']) : null,
      'summary' => isset($post['summary']) ? $post['summary'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'weight' => isset($post['weight']) ? intval($post['weight']) : null,
      'pvid' => isset($post['pvid']) ? intval($post['pvid']) : null,
      'author' => isset($post['author']) ? $post['author'] : null,
      'source' => isset($post['source']) ? $post['source'] : null,
      'created' => TIMESTAMP,
    );
    $db->insert('articles', $filter);
    return $db->lastInsertId();
  }

  /**
   * 删除文章信息
   * @param int $aid 文章ID
   */
  public function deleteArticle($aid)
  {
    global $db;
    $db->delete('articles', array('aid' => intval($aid)));
    cache::remove('article-' . $aid);
    return $db->affected();
  }
  
  /**
   * 更新点击次数
   * @param int $aid 文章ID
   */
  public function updateVisits($aid)
  {
  	global $db;
  	$articleInfo = $this->getArticleInfo($aid);
  	if(isset($articleInfo) && $articleInfo)
  	{
  		$visits = $articleInfo->visits + 1;
  		$db->exec("UPDATE `articles` SET `visits` = " . $visits . " WHERE `aid` = " . $aid);
  	}
  	return true;
  }

  /**
   *
   * 获取文章分类列表
   */
  public function getArticleTypeList()
  {
    global $db;
    static $new_array;
    if (!isset($new_array)) {
      $cacheId = 'article-types';
      if ($cache = cache::get($cacheId)) {
        $new_array = $cache->data;
      } else {
        $sql = "SELECT t.*, COUNT(a.aid) count FROM articles_type t LEFT JOIN articles a ON t.atid = a.atid GROUP BY t.atid";
        $result = $db->query($sql);
        $array = $result->allWithKey('atid');
        $new_array = array();
        foreach ($array as $k => $v) {
          $v->url = 'articletype/' . $v->path_alias . '.html';
          if(!$v->parent) {
            $new_array[$k] = $v;
            $new_array[$k]->allname = $new_array[$k]->fullname = $v->name;
            foreach ($array as $k2 => $v2) {
              if ($v2->parent == $k) {
                $new_array[$k2] = $v2;
                $new_array[$k2]->allname = '┗ '.$v2->name;
                $new_array[$k2]->fullname = $v->name.' -> '.$v2->name;
                $new_array[$k]->count += $v2->count;
                $new_array[$k]->typecount++;
                unset($array[$k2]);
              }
            }
          }
        }
      }
    }
    return $new_array;
  }

  /**
   *
   * Enter description here ...
   * @param $atid
   */
  public function getArticleTypeUnderArray($atid)
  {
    global $db;
    $db->where('parent', $atid);
    $result = $db->get('articles_type');
    $array = $result->allWithKey('atid');
    $new_array = array();
    $new_array[] = $atid;
    foreach ($array as $k => $v) {
      $new_array[] = $k;
    }
    return $new_array;
  }

  /**
   *
   * 获取文章分类信息
   * @param unknown_type $atid
   */
  public function getArticleTypeInfo($atid)
  {
    $list = $this->getArticleTypeList();
    return isset($list[$atid]) ? $list[$atid] : false;
  }

  public function getArticleTypeInfoByPath($path)
  {
     global $db;
     $db->select('atid');
     $db->where('path_alias', $path);
     $result = $db->get('articles_type');
     $atid = $result->one();
     if (isset($atid) && $atid) {
       return $this->getArticleTypeInfo($atid);
     }
     return false;
  }

  public function getArticleTypeInfoByTypeid($type_id)
  {
    static $list;
    if (!isset($list)) {
      $cacheId = 'article-types-ids';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        global $db;
        $result = $db->query('SELECT atid, type_id FROM articles_type WHERE type_id <> ""');
        $list = $result->columnWithKey('type_id');
        cache::save($cacheId, $list);
      }
    }
    return isset($list[$type_id]) ? $list[$type_id] : false;
  }

 /**
   *
   * 更新文章分类信息
   * @param unknown_type $aid
   * @param unknown_type $post
   */
  public function updateArticleType($atid, $post = array())
  {
    global $db;
    $filter = array(
      'name' => isset($post['name']) ? $post['name'] : null,
      'parent' => isset($post['parent']) ? intval($post['parent']) : null,
      'type_id' => isset($post['type_id']) ? $post['type_id'] : null,
      'path_alias' => isset($post['path_alias']) ? $post['path_alias'] : null,
      'pvid' => isset($post['pvid']) ? intval($post['pvid']) : 0,
    );
    $db->update('articles_type', $filter, array('atid' => $atid));
    cache::remove('article-types');
    cache::remove('article-types-ids');
    return $db->affected();
  }

  /**
   *
   * 新增文章分类信息
   * @param unknown_type $post
   */
  public function insertArticleType($post)
  {
    global $db;
    $filter = array(
      'name' => isset($post['name']) ? $post['name'] : null,
      'parent' => isset($post['parent']) ? intval($post['parent']) : null,
      'type_id' => isset($post['type_id']) ? $post['type_id'] : null,
      'path_alias' => isset($post['path_alias']) ? $post['path_alias'] : null,
    );
    $db->insert('articles_type', $filter);
    cache::remove('article-types');
    cache::remove('article-types-ids');
    return $db->lastInsertId();
  }

 /**
   *
   * 删除文章分类信息
   * @param $aid
   */
  public function deleteArticleType($atid)
  {
    global $db;
    $db->delete('articles_type', array('atid' => $atid));
    $reslut1 = $db->affected();
    if ($reslut1) {
      $db->where('atid' , $atid);
      $result = $db->get('articles');
      $arr = $result->all();
      foreach ($arr as $k => $v) {
        cache::remove('article-' . $v->aid);
      }
      $db->delete('articles', array('atid' => $atid));
      $db->where('parent', $atid);
      $result = $db->get('articles_type');
      $array = $result->allWithKey('atid');
      foreach ($array as $k=> $v) {
        $db->delete('articles_type', array('atid' => $k));
        $db->where('atid' , $k);
        $result = $db->get('articles');
        $arr = $result->all();
        foreach ($arr as $k => $v) {
          cache::remove('article-' . $v->aid);
        }
        $db->delete('articles', array('atid' => $k));
      }
    }
    cache::remove('article-types');
    cache::remove('article-types-ids');
    return $reslut1;
  }

 /**
   *
   * 获取搜索后条件显示
   * @param array $filter
   */
  public function getSelectHtml($filter, $posturl)
  {
    $selectHtml = '';
    $filter = array_filter($filter, "Common_Model::filterArray");
    if(!empty($filter)) {
      $selectHtml = '<b>'.t('Select Term').'（<a href="'.url('admin/content/firstList/all').'">'.t('Clear Away').'</a>）：</b>';
      foreach ($filter as $key => $dl) {
        if (isset($dl) && $dl !='') {
          if ($key =='status') {
            $dl == '1' ? $dl = t('Published') : $dl=  t('Unpublish');
          }
          $selectHtml .= $this->getSelectHtmlCeil($key, $dl, $posturl).' ';
        }
      }
    }
    return $selectHtml;
  }

  /**
   *
   * 获取搜索后条件显示具体实现
   * @param string $key
   * @param string $value
   */
  public function getSelectHtmlCeil($key, $value = "", $posturl)
  {
    switch ($key){
      case 'title' : return '<span>'.t('Article Title').'（'.$value.'）<a href="'.url('admin/content/firstList/'.$key.'/'.$posturl).'">×</a></span>';
      case 'atid' : return '<span>'.t('Article Type').'（'.$value[0].'）<a href="'.url('admin/content/firstList/'.$key.'/'.$posturl).'">×</a></span>';
      case 'status' : return '<span>'.t('Article Status').'（'.$value.'）<a href="'.url('admin/content/firstList/'.$key.'/'.$posturl).'">×</a></span>';
    }
  }

  /**
   *
   * 获取相关文章信息
   * @param unknown_type $aid
   * @param unknown_type $page
   * @param unknown_type $pageRows
   */
  public function getArticleRelated($aid = 0)
  {
    global $db;
    if ($aid) {
      $db->where('r.aid', $aid);
    }
    $db->join('articles a', 'r.related_aid = a.aid');
    $result = $db->get('articles_relations r');
    return $result->all();
  }

  /**
   *
   * 删除文章关联信息
   * @param unknown_type $aid
   */
  public function deleteArticleRelated($aid = 0)
  {
    global $db;
    $db->delete('articles_relations', array('aid' => $aid));
  }

  /**
   *
   * 新增文章关联信息
   * @param $aid
   * @param $post
   */
  public function insertArticleRelated($aid, $post)
  {
    global $db;
    if (isset($post) && $post) {
      if (is_object($post)) {
        foreach ($post as $k => $v) {
          if (isset($v->aid) && $v->aid) {
            $db->insert('articles_relations', array('aid' => intval($aid), 'related_aid' => intval($v->aid)));
          }
        }
      } else {
        $db->insert('articles_relations', array('aid' => intval($aid), 'related_aid' => intval($post)));
      }
    }
  }

  /**
   *
   * 获取文章的相关商品
   * @param unknown_type $aid
   * @param unknown_type $page
   * @param unknown_type $pageRows
   */
  public function getArticleProductRelated($aid = 0)
  {
    global $db;
    if ($aid) {
      $db->where('r.aid', $aid);
    }
    $db->join('products p', 'r.pid = p.pid');
    $result = $db->get('articles_products r');
    $productList = $result->all();
    foreach ($productList as $key => $product)
    {
    	 $product->directory_tid =  $product->directory_tid4 ? $product->directory_tid4 : (
            $product->directory_tid3 ? $product->directory_tid3 : (
              $product->directory_tid2 ? $product->directory_tid2 : (
                $product->directory_tid1 ? $product->directory_tid1 : 0
              )
            )
          );
          if ($product->directory_tid) {
            $taxonomyInstance = Taxonomy_Model::getInstance();
            $term = $taxonomyInstance->getTermInfo($product->directory_tid);
            $termPathAlias = isset($term->path_alias) && $term->path_alias !== '' ? $term->path_alias : 'product';
          } else {
            $termPathAlias = 'product';
          }
    	
    		$productList[$key]->url =  $termPathAlias . '/' . ($product->path_alias !== '' ? $product->path_alias : $product->pid) . '.html';
    }
    return $productList;
  }

 /**
   *
   * 删除文章商品关联信息
   * @param unknown_type $aid
   */
  public function deleteArticleProductRelated($aid = 0)
  {
    global $db;
    $db->delete('articles_products', array('aid' => $aid));
  }

  /**
   *
   * 新增文章商品关联信息
   * @param $aid
   * @param $post
   */
  public function insertArticleProductRelated($aid, $post)
  {
    global $db;
    if (is_object($post)) {
      foreach ($post as $k => $v) {
        $db->insert('articles_products',array('aid' => intval($aid), 'pid' => intval($v->pid)));
      }
    } else {
      $db->insert('articles_products',array('aid' => intval($aid), 'pid' => intval($post)));
    }
  }

  /**
   *
   * 获取页面信息列表
   * @param unknown_type $page
   * @param unknown_type $pageRows
   */
  public function getPageList($page, $pageRows)
  {
    global $db;
    static $array;
    if (!isset($array)) {
      $db->orderby('visible DESC, weight DESC, pid DESC');
      if($pageRows) {
        $db->limitPage($pageRows, $page);
      }
      $result = $db->get('pages');
      $array = $result->allWithKey('pid');
      foreach ($array as $k => $v) {
        $array[$k]->url = $array[$k]->path_alias.'.html';
      }
    }
    return $array;
  }

  /**
   *
   * 获取页面信息列表总数
   */
  public function getPageCount()
  {
    global $db;
    static $num;
    if (!isset($num)) {
      $db->select('COUNT(*) num');
      $result = $db->get('pages');
      $num = $result->one();
    }
    return $num;
  }

  /**
   *
   * 获取页面信息，根据页面ID
   * @param unknown_type $pid
   */
  public function getPageInfo($pid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$pid])) {
      $cacheId = 'page-' . $pid;
      if ($cache = cache::get($cacheId)) {
        $list[$pid] = $cache->data;
      } else {
        $db->where('pid', $pid);
        $result = $db->get('pages');
        $list[$pid] = $result->row();
        if ($list[$pid]) {
          $list[$pid]->url = $list[$pid]->path_alias.'.html';
        }
        cache::save($cacheId,  $list[$pid]);
      }
    }
    return $list[$pid];
  }

  /**
   *
   * 获取页面信息，根据别名
   * @param unknown_type $pathAlias
   */
  public function getPageInfoBypathAlias($pathAlias)
  {
    global $db;
    static $list = array();
    if (!isset($list[$pathAlias])) {
      $db->where('path_alias', $pathAlias);
      $result = $db->get('pages');
      $list[$pathAlias] = $result->row();
      if ($list[$pathAlias]) {
        $list[$pathAlias]->url = $list[$pathAlias]->path_alias.'.html';
      }
    }
    return $list[$pathAlias];
  }

  /**
   *
   * 根据 pid 删除指定页面信息
   * @param $pid
   */
  public function deletePage($pid)
  {
    global $db;
    $db->delete('pages', array('pid' => $pid));
    cache::remove('page-' . $pid);
    return $db->affected();
  }

  /**
   *
   * 新增页面信息
   * @param unknown_type $post
   */
  public function insertPage($post)
  {
    global $db;
    if (!$post[page_title]) {
      return false;
    }
    $set = array(
      'pvid' => isset($post['pvid']) ? intval($post['pvid']) : null,
      'title' => isset($post['page_title']) ? $post['page_title'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'path_alias' => isset($post['path_alias']) ? $post['path_alias'] : null,
      'weight' => isset($post['weight']) ? intval($post['weight']) : null,
      'visible' => isset($post['visible']) ? intval($post['visible']) : null,
    );
    $db->insert('pages', $set);
    cache::remove('static-routers');
    return $db->lastInsertId();
  }

  /**
   *
   * 修改页面信息
   * @param unknown_type $pid
   * @param unknown_type $post
   * @param unknown_type $isfilternull
   */
  public function updatePage($pid, $post, $isfilternull = 0)
  {
    global $db;
    if (!$post[page_title]) {
      return false;
    }
    $set = array(
      'pvid' => isset($post['pvid']) ? intval($post['pvid']) : null,
      'title' => isset($post['page_title']) ? $post['page_title'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'path_alias' => isset($post['path_alias']) ? $post['path_alias'] : null,
      'weight' => isset($post['weight']) ? intval($post['weight']) : null,
      'visible' => isset($post['visible']) ? intval($post['visible']) : null,
    );
    if($isfilternull) {
      $set = array_filter($set, "Common_Model::filterArray");
    }
    $db->update('pages', $set ,array('pid' => $pid));
    cache::remove('page-' . $pid);
    cache::remove('static-routers');
    return $db->affected();
  }

  /**
   *
   * 获取页面别名信息
   * @param $pathAlias
   */
  public function getPagePathAlias($pathAlias, $pid = 0)
  {
    $pageInfo = $this->getPageInfoBypathAlias($pathAlias);
    if ($pageInfo && $pid != $pageInfo->pid) {
      return $pathAlias.'-'.$pageInfo->pid;
    } else {
      return $pathAlias;
    }
  }

  /**
   * 获取上一篇下一篇的文章信息
   */
  public function getClosesArticle(&$articleInfo, $aid, $atid = null)
  {
    global $db;
    $sql = "SELECT aid FROM articles where 1";
    if (isset($atid) && $atid) {
      $sql2 = $sql . " and atid = '" . $atid . "'";
    }
    //下一篇文章
    $result = $db->query($sql2 . " and aid > '" . $aid . "' ORDER BY aid ASC LIMIT 1");
    $nextAid = $result->one();
    if (!isset($nextAid) || !$nextAid) {
      $result = $db->query($sql . " and aid > '" . $aid . "' ORDER BY aid ASC LIMIT 1");
      $nextAid = $result->one();
    }
    if (!isset($nextAid) || !$nextAid) {
      $result = $db->query($sql . " ORDER BY aid ASC limit 1");
      $nextAid = $result->one();
    }
    if (isset($nextAid) && $nextAid) {
      $article = $this->getArticleInfo($nextAid);
      $articleInfo->next = $this->getArticleInfo($nextAid);
    }
    //上一篇文章
    $result = $db->query($sql2 . " and aid < '" . $aid . "' ORDER BY aid DESC LIMIT 1");
    $prevAid = $result->one();
    if (!isset($prevAid) || !$prevAid) {
      $result = $db->query($sql . " and aid < '" . $aid . "' ORDER BY aid DESC  LIMIT 1");
      $prevAid = $result->one();
    }
    if (!isset($prevAid) || !$prevAid) {
      $result = $db->query($sql . " ORDER BY aid DESC limit 1");
      $prevAid = $result->one();
    }
    if (isset($prevAid) && $prevAid) {
      $article = $this->getArticleInfo($prevAid);
      $articleInfo->prev = $this->getArticleInfo($prevAid);
    }

  }
}