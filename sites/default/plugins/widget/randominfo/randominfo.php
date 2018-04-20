<?php
class RandomInfo extends Widget_Abstract
{
  var $_instance;

  public function getInfo($type, $filter = array(), $pageRows = 20, $ifids = true)
  {
    global $db;
    static $list = array();
    $productInstance = Product_Model::getInstance();
    $uri = Bl_Core::getUri();
    $path = md5($uri);
    $cacheId = $path . '-' . $type;
    if ($cache = cache::get($cacheId)) {
      $randomInfo = $cache->data;
    } else {
      if (!isset($list[$type])) {
        $db->select('data');
        $db->where('path', $path);
        $result = $db->get('widget_random');
        $randomIds = $result->one();
        $list[$type] = $randomIds;
      } else {
        $randomIds = $list[$type];
      }
      $randomIds = unserialize($randomIds);
      if (!isset($randomIds[$type]) || !$randomIds[$type]) {
        $randomInfo = $this->getRandInfo($type, $filter, $pageRows);
        if ($ifids) {
          if (isset($randomInfo) && $randomInfo) {
            foreach ($randomInfo as $k => $v) {
              $newrandomIds[$k] = $k;
            }
            $randomIds[$type] = $newrandomIds;
            $this->saveRandInfo($path, $randomIds, $uri);
          }
          cache::save($cacheId, $randomInfo);
        } else {
          $randomIds[$type] = $randomInfo;
          $this->saveRandInfo($path, $randomIds, $uri);
          cache::save($cacheId, $randomIds[$type]);
        }
        return $randomInfo;
      }
    }
    if ($ifids) {
      if (isset($randomIds[$type]) && $randomIds[$type]) {
        foreach ($randomIds[$type] as $k => $v) {
        	$object = $this->getObjectInfo($type, intval($v));
        	if($object){
        		$randomInfo[$k] = $this->getObjectInfo($type, intval($v));
        	}
        }
      }
    } else {
      $randomInfo = $randomIds[$type];
    }
    return $randomInfo;
  }

  private function getObjectInfo($type, $id)
  {
    $types = explode('_', $type);
    $id = intval($id);
    if ($types[0] == 'products') {
      $productInstance = Product_Model::getInstance();
      return $productInstance->getProductInfo($id);
    } elseif ($types[0] == 'articles') {
      $articleInstance = Content_Model::getInstance();
      return $articleInstance->getArticleInfo($id);
    } elseif ($types[0] == 'comments') {
      $commentInstance = Comment_Model::getInstance();
      return $commentInstance->getCommentInfo($id);
    }
  }

  private function getRandInfo($type, $filter, $pageRows = 20)
  {
    $types = explode('_', $type);
    if ($types[0] == 'products') {
      return $this->getRandProduct($filter, $pageRows);
    } elseif ($types[0] == 'articles') {
      return $this->getRandArticle($filter, $pageRows);
    } elseif ($types[0] == 'comments') {
      return $this->getRandComment($filter, $pageRows);
    } elseif ($types[0] == 'tags') {
      return widgetCallFunction('seotags', 'getTagsRelateKeyArray',$filter['keywords'], $filter['tid'], $filter['level'], $pageRows);
    } elseif ($types[0] == 'tagClouds') {
      $result = widgetCallFunction('seotags', 'getTagsClouds',$filter['keywords'], $filter['tid'], $pageRows);
      foreach ($result as $k => $v) {
        $result[$k] = array_values($v);
      }
      return $result;
    }
  }

  private function getRandProduct($filter, $pageRows = 20)
  {
    $taxonomyInstance = Taxonomy_Model::getInstance();
    $productInstance = Product_Model::getInstance();
    $filter['orderby'] = 'rand()';
    if (isset($filter['directory_tid']) && $filter['directory_tid']) {
      $termInfo = $taxonomyInstance->getTermInfo($filter['directory_tid']);
      if (isset($termInfo) && $termInfo) {
        if (!$termInfo->ptid1) {
          $parent[] = $termInfo->tid;
        } else if (!$termInfo->ptid2) {
          $parent[] = $termInfo->ptid1;
          $parent[] = $termInfo->tid;
        } else if (!$termInfo->ptid3) {
          $parent[] = $termInfo->ptid1;
          $parent[] = $termInfo->ptid2;
          $parent[] = $termInfo->tid;
        } else {
          $parent[] = $termInfo->ptid1;
          $parent[] = $termInfo->ptid2;
          $parent[] = $termInfo->ptid3;
          $parent[] = $termInfo->tid;
        }
      }
      $parent = $parent ? $parent : array();
      if(isset($filter['lever'])){
	      if ($filter['level'] == 1) {
	        $filter['tids'] = isset($parent[0]) ? $parent[0] : $filter['directory_tid'];
	      }elseif ($filter['level'] == 'self') {
	        $filter['tids'] = $filter['directory_tid'];
	      }elseif ($filter['level'] == 'all') {
	        $filter['tids'] = null;
	      }
      }
    }
    if(isset($filter['tname']) && $filter['tname']) {
    	$randomInfo = $productInstance->getProductsListBySpecial($filter, 1, $pageRows);
    } else {
    	$randomInfo = $productInstance->getProductsList($filter, 1, $pageRows);
    }
    return isset($randomInfo) ? $randomInfo : array();
  }

  private function getRandArticle($filter, $pageRows = 20)
  {
    $articleInstance = Content_Model::getInstance();
    $filter['orderby'] = 'rand()';
    return $articleInstance->getArticleList($filter, 1, $pageRows);
  }

  private function getRandComment($filter, $pageRows = 20)
  {
    $commentInstance = Comment_Model::getInstance();
    $filter['orderby'] = 'rand()';
    $filter['status'] = 1;
    if (isset($filter['pid']) && $filter['pid']) {
      $randomInfo = $commentInstance->getCommentsListByProductId($filter['pid'], $filter, 1, $pageRows);
    } else {
      $randomInfo = $commentInstance->getCommentsList(1, $pageRows, 1, $filter);
    }
    return $randomInfo;
  }

  private function saveRandInfo($path, $data, $uri = null)
  {
    global $db;
    $data = serialize($data);
    $db->where('path', $path);
    $result = $db->get('widget_random');
    if (!(boolean)$result->row()) {
      $db->insert('widget_random', array('path' => $path, 'uri' => $uri,  'data' => $data));
      $db->lastInsertId();
    } else {
      $db->update('widget_random', array('data' => $data), array('path' => $path));
      return $db->affected();
    }
  }

  public function install()
  {
    global $db;
    $sql = 'CREATE TABLE IF NOT EXISTS `widget_random`(     `path` CHAR(32) NOT NULL ,  `uri` CHAR(128) NOT NULL ,   `data` TEXT ,     PRIMARY KEY (`path`)  );';
    $db->exec($sql);
  }

  public function uninstall()
  {
    global $db;
    $sql = 'DROP TABLE `widget_random`;';
    $db->exec($sql);
  }

  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    $this->_instance = $instance;
    $this->DoExecute();
  }

  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $this->_instance = $instance;
    $this->DoExecute();
  }

  /**
   * 执行功能
   */
  public function DoExecute()
  {
    $router = Bl_Core::getRouter();
    $args = $router['arguments'];
    $action = $args[1];
    $func = $action.'Action';
    if (!method_exists($this, $func)) {
      gotoUrl('admin/site/widgetedit/randominfo/getrandomlist/');
    }
    array_shift($args);
    array_shift($args);
    call_user_func_array(array($this, $func), $args);
  }
  
  public function getRandomListAction($page = 1, $uri = null)
  {
    $pageRows = 20;
    if ($this->_instance->isPost()) {
      $uri = trim($_POST['uri']);
    }
    $randomList = $this->_getRandomList($page, $pageRows, $uri);
    $randomCount = $this->getRandomCount($uri);
    $this->_instance->view->render('../plugins/widget/randominfo/randominfolist.phtml', array(
      'uri' => isset($uri) ? $uri : null,
      'randomList' => $randomList,
      'pagination' => pagination('admin/site/widgetedit/randominfo/getRandomList/%d/' . $uri, $randomCount, $pageRows, $page),
    ));
    exit;
  }



  public function editRandomInfoAction($path)
  {
    if ($this->_instance->isPost()){
      $post = $_POST;
      foreach ($post as $k => $v) {
        $post[$k] = explode(',', $v);
        foreach ($post[$k] as $k2 => $v2) {
          if (preg_match ("/:/i", $v2)) {
            $post[$k][$k2] = explode(':', $v2);
            foreach ($post[$k][$k2] as $k3 => $v3) {
              if (intval($post[$k][$k2][$k3])) {
                $newpost[$k][$k2][$v3] = intval($post[$k][$k2][$k3]);
              }
            }
          } else {
            foreach ($post[$k] as $k2 => $v2) {
              if (intval($post[$k][$k2])) {
                $newpost[$k][$v2] = intval($post[$k][$k2]);
              }
            }
          }
        }
      }
      $result = $this->saveRandInfo($path, $newpost);
      if (isset($result) && $result > 0) {
        setMessage('保存随机信息成功');
      } elseif (isset($result) && $result == 0) {
        setMessage('没有任何更改');
      }else {
        setMessage('保存随机信息失败', 'error');
      }
      gotoUrl('admin/site/widgetedit/randominfo/getrandomlist/');
    } else {
      $randomInfo = $this->getRandomInfo($path);
      $this->_instance->view->render('../plugins/widget/randominfo/randominfoinfo.phtml', array(
        'randomInfo' => $randomInfo,
      ));
    }
  }


  private function _getRandomList($page = 1, $pageRows = null, $uri = null)
  {
    global $db;
    if (isset($uri) && $uri == '') {
      $db->where('uri', '');
    } else if (isset($uri)) {
      $db->where('uri like', '%' . $uri . '%' );
    }
    if (isset($pageRows)) {
      $db->limitPage($pageRows, $page);
    }
    $result = $db->get('widget_random');
    return $result->all();
  }

  private function getRandomCount($uri = null)
  {
    global $db;
    $db->select('COUNT(0)');
    if (isset($uri) && $uri == '') {
      $db->where('uri', '');
    } else if (isset($uri)) {
      $db->where('uri like', '%' . $uri . '%' );
    }
    $result = $db->get('widget_random');
    return $result->one();
  }

  private function getRandomInfo($path)
  {
    global $db;
    $db->where('path', $path);
    $result = $db->get('widget_random');
    $randomInfo = $result->row();
    $randomInfo->data = unserialize($randomInfo->data);
    return $randomInfo;
  }

  private function deleteRandomInfoAction($path)
  {
     global $db;
     $db->delete('widget_random', array('path' => $path));
     if ($db->affected()) {
       setMessage('删除成功');
     }
     gotoUrl('admin/site/widgetedit/randominfo/getrandomlist/');
  }

  public function clearAllRandomAction()
  {
  	global $db;
    $sql = 'TRUNCATE TABLE `widget_random`;';
    $db->exec($sql);
    setMessage('随机信息清除成功');
     gotoUrl('admin/site/widgetedit/randominfo/getrandomlist/');
  }

}