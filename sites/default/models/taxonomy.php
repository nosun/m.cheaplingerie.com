<?php
class Taxonomy_Model extends Bl_Model
{
  const TYPE_TAG = 1;
  const TYPE_BRAND = 2;
  const TYPE_DIRECTORY = 3;
  const TYPE_RECOMMEND = 4;

  public $vocabularyType = array(
    self::TYPE_TAG => 'tag',
    self::TYPE_BRAND => 'brand',
    self::TYPE_DIRECTORY => 'directory',
    self::TYPE_RECOMMEND => 'recommend',
  );

  /**
   * @return Taxonomy_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取所有分类信息列表
   * @return object 分类列表信息
   */
  public function getVocabularyList($type = null)
  {
    global $db;
    static $list;
    if (!isset($list)) {
      $cacheId = 'vocabulary-list';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        $result = $db->query('SELECT v.*, COUNT(t.vid) count FROM vocabulary v LEFT JOIN terms t ON v.vid=t.vid
        GROUP BY v.vid ORDER BY v.type DESC, v.vid DESC');
        $list = $result->allWithKey('vid');
        cache::save($cacheId, $list);
      }
    }
    return $list;
  }

  /**
   * 获取单个分类的信息(按ID)
   * @param int $vid 分类ID
   * @return object 单笔分类信息
   */
  public function getVocabularyInfoByVid($vid)
  {
    $list = $this->getVocabularyList($vid);
    return isset($list[$vid]) ? $list[$vid] : false;
  }

  /**
   * 获取分类的信息 (按类型)
   * @param string $type 分类类型
   * @return object 单笔分类信息
   */
  public function getVocabularyInfoByType($type)
  {
    static $list;
    if (!isset($list)) {
      $cacheId = 'vocabulary-types';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        global $db;
        $result = $db->query('SELECT vid, type FROM vocabulary WHERE type = "' . $db->escape($type) . '"');
        $list = $result->columnWithKey('type');
        cache::save($cacheId, $list);
      }
    }
    if (isset($list[$type])) {
      return $this->getVocabularyInfoByVid($list[$type]);
    } else {
      return false;
    }
  }

  /**
   * 获取分类的信息（按照名字）
   * @param string $name 分类名称
   * @return object 单笔分类的信息
   */
  public function getVocabularyInfoByName($name)
  {
    static $list;
    if (!isset($list)) {
      $cacheId = 'vocabulary-names';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        global $db;
        $result = $db->query('SELECT vid, name FROM vocabulary WHERE name = "' . $db->escape($name) . '"');
        $list = $result->columnWithKey('name');
        cache::save($cacheId, $list);
      }
    }
    if (isset($list[$name])) {
      return $this->getVocabularyInfoByVid($list[$name]);
    } else {
      return false;
    }
  }

  /**
   * 新增分类
   * @param array $post 分类名称,$post['name']必须存在
   * @return boolean
   */
  public function insertVocabulary($post)
  {
    global $db;
    if ($post['name'] == "") {
      return false;
    } else if (!isset($post['type'])) {
      $post['type'] = 0;
    }
    $set = array(
      'name' => $post['name'],
      'name_cn' => $post['name_cn'],
      'type' => $post['type'],
    );
    $db->insert('vocabulary', $set);
    return $db->actived();
  }

  /**
   * 修改分类
   * @param int $vid 分类ID
   * @param array $post 分类信息
   * @return boolean
   */
  public function updateVocabulary($vid, $post)
  {
    global $db;
    $set = array(
      'name' => $post['name'],
      'name_cn' => $post['name_cn'],
    );
    $db->update('vocabulary', $set, array('vid' => $vid));
    cache::remove('vocabulary-list');
    return (boolean)$db->actived();
  }

  /**
   * 删除分类
   * @param int $vid 分类ID
   * @return boolean
   */
  public function deleteVocabulary($vid)
  {
    global $db;
    $db->delete('vocabulary', array('vid' => $vid));
    $affected = (boolean)$db->affected();
    if ($affected) {
      $this->deleteUnderVocabulary($vid);
    }

    return $affected;
  }

  /**
   * 删除分类下的分类词
   * @param int $vid 分类词ID
   * @return array
   */
  private function deleteUnderVocabulary($vid)
  {
    global $db;
    $sql = 'SELECT * FROM terms WHERE vid = "' . $db->escape($vid) . '" ';
    $result = $db->query($sql);
    $arr = $result->all();
    foreach($arr as $key => $dl){
      $this->deleteTermsHierarchy($dl->tid);
      cache::remove('term-' . $dl->tid);
      cache::remove('admin-term-' . $dl->tid);
    }
    $db->delete('terms', array('vid' => $vid));
    return (boolean)$db->affected();
  }

  /**
   * 获取特定分类下的所有的分类词列表
   * @param int $vid 分类ID
   * @param boolean $tree 是否产生树结构
   * @return object 分类词列表对象
   */
  public function getTermsList($vid, $tree = true, $getProductNum = false) {
    global $db;
    static $termslistStatic = array();
    $router = Bl_Core::getRouter();
    $skey = $vid . (boolean)$tree. (boolean)$getProductNum . $router['folder'];
    if (!isset($termslistStatic[$skey])) {
      $cacheId = 'termList' . $vid . (boolean)$tree . (boolean)$getProductNum . $router['folder'];
      if ($cache = cache::get($cacheId)) {
        $termslistStatic[$skey] = $cache->data;
      } else {
        $db->where('vid', $vid);
        if ($router['folder'] != 'admin') {
          $db->where('visible', 1);
        }
        $db->orderby('weight DESC, tid DESC');
        $result = $db->get('terms');
        $termsList = $result->allWithKey('tid');
        foreach ($termsList as $k => $v) {
          $termsList[$k]->url = 'category/'.$v->path_alias.'.html';
          if ($getProductNum) {
            $termsList[$k]->productNum = $this->getProductNumUnderTerm($v->tid, $v->vid);
          }
          $termsList[$k]->parent = $v->ptid3 ? $v->ptid3 : (
            $v->ptid2 ? $v->ptid2 : (
              $v->ptid1 ? $v->ptid1 : 0
            )
          );
        }
        if ($tree) {
          $chirdren = array();
          $new_termList = array();
          foreach ($termsList as $key => $dl) {
            $chirdren[$dl->parent][] = $dl->tid;
            $termsList[$key]->sub = array();
            $termsList[$key]->count = 0;
          }
          foreach ($chirdren as $key => $dl) {
            if ($key!=0) {
              $termsList[$key]->count = count($dl);
            }
            foreach($dl as $key2 => $dll){
              if ($key == 0) {
                $new_termList[$dll] = $termsList[$dll];
              } else {
                $termsList[$key]->sub[$dll] = $termsList[$dll];
                $termsList[$key]->count += (isset($chirdren[$dll]) ? count($chirdren[$dll]) : 0);
              }
            }
          }
          $termsList = $new_termList;
        }
        $termslistStatic[$skey] = $termsList;
      }
    }
    return $termslistStatic[$skey];
  }

  public function getTermsList_back($vid, $tree = true, $getProductNum = false)
  {
    global $db;
    static $termslistc = array();
    $arrkey = $vid . (boolean)$tree;
    if (!isset($termslistc[$arrkey])) {
      $router = Bl_Core::getRouter();
      $sqladd = '';
      if ($router['folder'] != 'admin') {
        $sqladd = 'and visible = 1';
      }
      static $list = array(), $treeList = array();
      $cacheId = 'termList' . $vid . (boolean)$tree . (boolean)$getProductNum . $router['folder'];
      if ($cache = cache::get($cacheId)) {
        $termslistc[$arrkey] = $cache->data;
      } else {
        if(!isset($list[$vid])){
          $sql = 'SELECT t.*,IFNULL(h.parent,"0") parent FROM terms t LEFT JOIN terms_hierarchy h ON t.tid = h.tid WHERE
            t.vid = "' . $db->escape($vid) . '" ' . $sqladd . ' ORDER BY weight DESC, tid DESC ';
          $result = $db->query($sql);
          $list[$vid] = $result->allWithKey('tid');
        }
        foreach ($list[$vid] as $k => $v) {
        	  $list[$vid][$k]->url = 'category/'.$list[$vid][$k]->path_alias.'.html';
        	  if ($getProductNum) {
        	    $list[$vid][$k]->productNum = $this->getProductNumUnderTerm($list[$vid][$k]->tid, $list[$vid][$k]->vid);
        	  }
        }
        $result = $list[$vid];
        if ($tree) {
          if (!isset($treeList[$vid])) {
            $arr = $termsList = $list[$vid];
    	      $chirdren = array();
    	      $new_termList = array();
    	      foreach ($termsList as $key => $dl) {
    	        $chirdren[$dl->parent][] = $dl->tid;
    	        $termsList[$key]->sub = array();
    	        $termsList[$key]->count = 0;
    	      }
    	      foreach ($chirdren as $key => $dl) {
    	        if ($key!=0) {
    	          $termsList[$key]->count = count($dl);
    	        }
    	        foreach($dl as $key2 => $dll){
    	          if ($key == 0) {
    	            $new_termList[$dll] = $termsList[$dll];
    	          } else {
    	            $termsList[$key]->sub[$dll] = $termsList[$dll];
    	            $termsList[$key]->count += (isset($chirdren[$dll]) ? count($chirdren[$dll]) : 0);
    	          }
    	        }
    	      }
    	      $treeList[$vid] = $new_termList;
          }
          $result = $treeList[$vid];
        }
        $termslistc[$arrkey] = $result;
        if ($router['folder'] != 'admin') {
          cache::save($cacheId, $result);
        }
      }
    }
    return $termslistc[$arrkey];
  }

  /**
   * 获取某分类类型下的分类词列表
   * @param string $type 分类类型
   * @param boolean $tree 是否产生树结构
   * @return array
   */
  public function getTermsListByType($type, $tree = true)
  {
    $vocabulary = $this->getVocabularyInfoByVid($type);
    if ($vocabulary) {
      return $this->getTermsList($vocabulary->vid, $tree);
    } else {
      return array();
    }
  }

  /**
   * 获取所有以$char开头的tag
   * 如果$char为null返回所有的tag
   * @param Taxonomy_Model::TYPE_* $type
   * @param 首字母 $char
   */
  public function getTagListByStartChar($type, $char = null) {
  	$vocabulary = $this->getVocabularyInfoByVid(Taxonomy_Model::TYPE_TAG);
  	global $db;
  	$db->where('vid', $vocabulary->vid);
  	$db->where('visible', 1);
  	if (isset($char)) {
  		$db->where('name like', $char . '%');
  	}
  	$db->orderby('weight DESC, tid DESC');
  	$result = $db->get('terms');
  	$tagList = $result->allWithKey('tid');
  	foreach ($tagList as $k => $v) {
  		$tagList[$k]->url = url($v->path_alias.'.html');
  	}
  	return $tagList;
  }

  public function getBrandListByProduct($vid, $directory_tid)
  {
    global $db;
    if (isset($directory_tid) && $directory_tid) {
      $termInfo = $this->getTermInfo($directory_tid);
       if(!$termInfo->ptid1) {
         $column = 'directory_tid1';
       } else if (!$termInfo->ptid2) {
         $column = 'directory_tid2';
       } else if (!$termInfo->ptid3) {
         $column = 'directory_tid3';
       } else {
         $column = 'directory_tid4';
       }
      $db->where('p.' . $column, $directory_tid);
    }
   	$db->select('DISTINCT(t.name) name, t.tid, t.path_alias');
    $db->where('t.vid', $vid);
    $router = Bl_Core::getRouter();
    if ($router['folder'] != 'admin') {
      $db->where('t.visible', 1);
    }
    $db->join('products p', 'p.brand_tid = t.tid');
    $result = $db->get('terms t');
    return $result->all();
  }
  
  /**
   * 获取同一品牌下的分类词列表
   * @param int $brand_id 品牌ID号
   * @return array
   */
  
  public function getTermsListByBrandId($brand_id)
  {
  	global $db;
  	$cacheId = "TermsListByBrnad". $brand_id;
  	if($cache = cache::get($cacheId)){
  		$brandTermsList = $cache->data;
  	}else{
			$db->select('directory_tid1,directory_tid2,directory_tid3,directory_tid4');
			$db->where('brand_tid', $brand_id);
			$db->where('directory_tid1 > ',0);
			$db->groupby('directory_tid1,directory_tid2,directory_tid3,directory_tid4');
			$result = $db->get('products');
			$tempTermsList = $result->all();
			$brandTermsList = array();
			$termsList = $this->getTermsList(Taxonomy_Model::TYPE_DIRECTORY, $tree = false, $getProductNum = false);
			foreach ($tempTermsList as $term)
			{
				$tid = $term->directory_tid4 ? $term->directory_tid4 : (
					$term->directory_tid3 ? $term->directory_tid3 : (
						$term->directory_tid2 ? $term->directory_tid2 : (
							$term->directory_tid1 ? $term->directory_tid1 : 0
						)
					)
				);
				$brandTermsList[] = $termsList[$tid];
			}
			cache::save($cacheId, $brandTermsList);
  	}
			return $brandTermsList;
  }

  /**
   * 生成 HTML 格式数组
   * @param mixed $tree
   * @return array
   */
  public function getTermsListForHtmlTree($tree)
  {
    if (!is_array($tree)) {
      $tree = $this->getTermsList($vid);
    }
    $directoryTreeList = array();
    foreach ($tree as $tid1 => $term1) {
      $directoryTreeList[$tid1] = $term1->name;
      if (isset($term1->sub) && !empty($term1->sub)) {
        $len2 = count($term1->sub);
        $key2 = 0;
        foreach ($term1->sub as $tid2 => $term2) {
          ++$key2;
          $directoryTreeList[$tid2] = (($key2 == $len2) ? '┗ ' : '┣ ') . $term2->name;
          if (isset($term2->sub) && !empty($term2->sub)) {
            $len3 = count($term2->sub);
            $key3 = 0;
            foreach ($term2->sub as $tid3 => $term3) {
              ++$key3;
              $directoryTreeList[$tid3] = (($key3 == $len3) ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '┃ ') . (($key3 == $len3) ? '┗ ' : '┣ ') . $term3->name;
              if (isset($term3->sub) && !empty($term3->sub)) {
                $len4 = count($term3->sub);
                $key4 = 0;
                foreach ($term3->sub as $tid4 => $term4) {
                  ++$key4;
                  $directoryTreeList[$tid4] = (($key4 == $len4) ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '┃┣  ') . (($key4 == $len4) ? '┗ ' : '┣ ') . $term4->name;
                }
              }
            }
          }
        }
      }
    }
    return $directoryTreeList;
  }

  /**
   * 获取分类词详细信息（通过TID）
   * @param int $tid 分类词ID 必填
   * @param int $vid 分类ID 可选，填写后检验 tid 与 vid 关系是否正确
   * @return object 分类词的信息
   */
  public function getTermInfo($tid, $vid = null)
  {
    global $db;
    static $list = array();
    $router = Bl_Core::getRouter();
    if(!isset($list[$tid])){
    	$cacheId = (isset($router['folder']) ?  $router['folder'] . '-' : null) . 'term-' . $tid;
      if ($cache = cache::get($cacheId)) {
        $list[$tid] = $cache->data;
      } else {
	      $db->where('tid', $tid);
	      if (isset($vid)) {
	        $db->where('vid', $vid);
	      }
        $router = Bl_Core::getRouter();
        if ($router['folder'] != 'admin') {
          $db->where('visible', 1);
        }
	      $db->join('files f', 'f.fid = t.fid', 'left');
	      $result = $db->get('terms t');
	      $list[$tid] = $result->row();
	      if (isset($list[$tid]) && $list[$tid]) {
	        $list[$tid]->url = 'category/'.$list[$tid]->path_alias.'.html';
	        $list[$tid]->productNum = $this->getProductNumUnderTerm($tid, $list[$tid]->vid);
	        $list[$tid]->childsTid=$this->getTermChildsByParent($tid);
	      }
        cache::save($cacheId,  $list[$tid]);
      }
    }
    return $list[$tid];
  }

  public function getProductNumUnderTerm($tid, $vid)
  {
     global $db;
     if ($tid && $vid) {
       if ($vid == Taxonomy_Model::TYPE_DIRECTORY) {
         $db->where('tid', $tid);
         $result = $db->get('terms');
         $termInfo = $result->row();
         if (isset($termInfo) && $termInfo) {
          if (!$termInfo->ptid1) {
            $filter2['directory_tid1'] = $termInfo->tid;
          } else if (!$termInfo->ptid2) {
            $filter2['directory_tid1'] = $termInfo->ptid1;
            $filter2['directory_tid2'] = $termInfo->tid;
          } else if (!$termInfo->ptid3) {
            $filter2['directory_tid1'] = $termInfo->ptid1;
            $filter2['directory_tid2'] = $termInfo->ptid2;
            $filter2['directory_tid3'] = $termInfo->tid;
          } else {
            $filter2['directory_tid1'] = $termInfo->ptid1;
            $filter2['directory_tid2'] = $termInfo->ptid2;
            $filter2['directory_tid3'] = $termInfo->ptid3;
            $filter2['directory_tid4'] = $termInfo->tid;
          }
        }
        $db->select('COUNT(0)');
        if (isset($filter2) && $filter2) {
          foreach ($filter2 as $key => $value) {
            if (isset($value) && $value !== '' && $value !== false) {
              $db->where($key, $value);
            }
          }
        }
        $db->where('status', 1);
         $result = $db->get('products');
         $num = $result->one();
       } elseif ($vid == Taxonomy_Model::TYPE_BRAND) {
         $db->select('COUNT(0)');
         $db->where('brand_tid' , $tid);
         $result = $db->get('products');
         $num = $result->one();
       } else {
         $db->select('COUNT(0)');
         $db->where('tp.tid', $tid);
         $db->join('terms_products tp', 'tp.pid = p.pid');
         $result = $db->get('products p');
         $num = $result->one();
       }
     }
     return isset($num) ? $num : 0;
  }

  /**
   * 获取分类词的详细信息（通过名字）
   * @param int $tid 分类词ID 必填
   * @param int $vid 分类ID 可选，填写后检验 tid 与 vid 关系是否正确
   * @return object 分类词的信息
   */
  public function getTermInfoByName($name, $vid = null, $parent = null)
  {
    global $db;
    static $list = array();
    if(!isset($list[$name])){
	    $sql = 'SELECT tid FROM terms ';
	    $sql .= 'WHERE name = "' . $db->escape($name) . '" ';
	    if (isset($parent) && $parent) {
        $sql .= 'AND (ptid1 = "' . $db->escape($parent) . '" OR ptid2 = "' . $db->escape($parent) . '" OR ptid3 = "' . $db->escape($parent) . '") ';
      }
      if (isset($vid)) {
        $sql .= 'AND vid = "' . $db->escape($vid) . '" ';
      }
      $result = $db->query($sql);
      $tid = $result->one();
      if (isset($tid) && $tid) {
        $list[$name] = $this->getTermInfo($tid, $vid);
      }
    }
    return $list[$name];
  }

 /**
   * 获取分类词的详细信息（通过别名）
   * @param int $tid 分类词ID 必填
   * @param int $vid 分类ID 可选，填写后检验 tid 与 vid 关系是否正确
   * @return object 分类词的信息
   */
  public function getTermInfoByPathAlias($pathAlias)
  {
    global $db;
    static $list = array();
    if (!isset($list[$pathAlias])) {
      $sql = 'SELECT tid FROM terms WHERE path_alias = "' . $db->escape($pathAlias) . '"';
      $result = $db->query($sql);
      $tid = $result->one();
      if (isset($tid) && $tid) {
        $list[$pathAlias] = $this->getTermInfo($tid);
      }else{
      	$list[$pathAlias] = false;
      }
    }
    return $list[$pathAlias];
  }

  /**
   * 获取分类词多级的上级ID（递归获取）
   * @param int $tid 分类词ID
   * @return array
   */
  public function getTermParents($tid ,$arr = null)
  {
    global $db;
    $termInfo = $this->getTermInfo($tid);
    $termInfo->ptid3 ? $arr[] = $termInfo->ptid3 : '';
    $termInfo->ptid2 ? $arr[] = $termInfo->ptid2 : '';
    $termInfo->ptid1 ? $arr[] = $termInfo->ptid1 : '';
    return $arr;
  }

  /**
   * 获取分类词上级ID
   * @param int $tid 分类词ID
   * @return int $parent 分类词的父ID
   */
  public function getTermParentByTid($tid)
  {
    global $db;
    $termInfo = $this->getTermInfo($tid);
    $parent = $termInfo->ptid3 ? $termInfo->ptid3 : (
            $termInfo->ptid2 ? $termInfo->ptid2 : (
              $termInfo->ptid1 ? $termInfo->ptid1 : 0
            )
          );
    return $parent;
  }

  /**
   * 获得分类词的所有子ID, added by 55feng (2010-10-14)
   * @param $parent
   * @param $childList, 保存所有子ID列表的引用
   */
  public function getTermChilds($parent, &$childList, $level = 1)
  {
    //TODU 需要传分类等级
    static $list = array();
    if (isset($list[$parent])) {
      return true;
    }
    $cacheId = 'term-childs-list';
    if (($cache = cache::get($cacheId)) && ($data = $cache->data) && isset($data[$parent])) {
      $childs = $data[$parent];
    } else {
      $childs = $this->getTermChildsByParent($parent);
      $data[$parent] = $childs;
      cache::save($cacheId, $data);
    }
    if (!is_array($childs) || count($childs) < 1) {
      return false;
    }
    $childList[$parent] = $childs;
    if ($level >= 3) {
      return false;
    }
    foreach($childs as $key => $child){
      $this->getTermChilds($child, $childList, ++$level);
    }
  }

  /**
   * 获得分类词的所有子ID 并放入一维数组中
   */
  public function getTermChildsToLinearArray ($tid) {
    global $db;
    $cacheId = 'TermChildsToLinear-' . $tid;
    if ($cache = cache::get($cacheId)) {
      $tids = $cache->data;
    } else {
      $db->where('tid', $tid);
      $result = $db->get('terms');
      $termInfo = $result->row();
      if(!$termInfo->ptid1) {
        $column = 'ptid1';
      } else if (!$termInfo->ptid2) {
        $column = 'ptid2';
      } else if (!$termInfo->ptid3) {
        $column = 'ptid3';
      }
      if (isset($column) && $column) {
        $db->select('tid');
        $db->where($column, $tid);
        $result = $db->get('terms');
        $tids = $result->column();
      }
      $tids[] = $tid;
      cache::save($cacheId, $tids);
    }
    return $tids;
  }


  /**
   * 获取分类词下级ID, added by 55feng (2010-10-14)
   * @param int $parent 分类词ID
   * @return int $tid 分类词的子ID
   */
  public function getTermChildsByParent($parent)
  {
    global $db;
    static $list = array();
    if(isset($list[$parent])){
      return $list[$parent];
    }
    $cacheId = 'termchilds-' . $parent;
    if ($cache = cache::get($cacheId)) {
      $list[$parent] = $cache->data;
    } else {
      if (isset($parent) && $parent) {
        $termInfo = $this->getTermInfo($parent);
         if($termInfo->ptid3 != 0){
           return array();
         }
         else if($termInfo->ptid2 != 0) {
           $column = 'ptid3';
         } else if ($termInfo->ptid1 != 0) {
           $column = 'ptid2';
         } else{
           $column = 'ptid1';
         } 
      }
      $sql = 'SELECT tid FROM terms WHERE ' . $column . ' = "' . $db->escape($parent) . '"';
      $result = $db->query($sql);
      $list[$parent] = $result->column();
      cache::save($cacheId,  $list[$parent]);
    }
    return $list[$parent];
  }
  
//   lzxsdl
  public function getTermDirectChildsByParent($parent){
  	global $db;
  	
  	if (isset($parent) && $parent) {
  		$termInfo = $this->getTermInfo($parent);
  		if($termInfo->ptid1 == 0){
  			$column = 'ptid1';
  			$nextcol = 'ptid2';
  		}
  		else if($termInfo->ptid2 == 0){
  			$column = 'ptid2';
  			$nextcol = 'ptid3';
  		}
  		else{
  			return array();
  		}
  	}
  	$sql = 'SELECT tid FROM terms WHERE ' . $column . ' = "' . $db->escape($parent) . '"'.' and ' . $nextcol . ' = "0" and visible=1 ';
  	$result = $db->query($sql);
  	$list[$parent] = $result->column();
  	return $list[$parent];
  }
  
  public function getTermSibling($tid, $explodeSelf = true){
    global $db;
    static $list = array();
    if(isset($list[$tid])){
      return $list[$tid];
    }
    
    $cacheId = 'termsiblings-' . $tid;
    if($explodeSelf){
      $cacheId = 'termsiblings-explode' . $tid;
    }
    
    if ($cache = cache::get($cacheId)) {
      $list[$tid] = $cache->data;
    } else {
      if (isset($tid) && $tid) {
        $pid = 0;
        $termInfo = $this->getTermInfo($tid);
         if($termInfo->ptid3){
           $column = 'ptid3';
           $pid = $termInfo->ptid3;
         }
         else if($termInfo->ptid2) {
           $column = 'ptid2';
           $pid = $termInfo->ptid2;
         } else if ($termInfo->ptid1) {
           $column = 'ptid1';
           $pid = $termInfo->ptid1;
         } else{
           $column = 'ptid3 = 0 AND ptid2 = 0 AND ptid1';
           $pid = 0;
         }
         
      $sql = 'SELECT tid FROM terms WHERE ' . $column . ' = ' . $db->escape($pid).' AND vid ='.$db->escape($termInfo->vid);
      if($explodeSelf){
        $sql.= ' AND tid <>'. $db->escape($tid);
      }
      $sql .= ' ORDER BY weight DESC, tid DESC';
      $result = $db->query($sql);
      $list[$tid] = $result->column();
      cache::save($cacheId,  $list[$tid]);
      }else{
        return array();
      }
    }
    return $list[$tid];
  }
  
  


  /**
   * 新增分类词
   * @param array $post 分类词信息
   * @return boolean
   */
  public function insertTerm($post)
  {
    global $db;
    $set = array(
      'vid' => $post['vid'],
      'ptid1' => $post['sclass1'],
      'ptid2' => $post['sclass2'],
      'ptid3' => $post['sclass3'],
      'fid' => intval($post['fid']),
      'pvid' => $post['pvid'],
      'name' => $post['name'],
      'description' => $post['description'],
      'path_alias' => trim(basename($post['path_alias'], '-')),
      'visible' => $post['visible'],
      'weight' => intval($post['weight']),
      'template' => $post['template'],
      'filepath' => $post['filepath'],
      'name_cn' => $post['name_cn'],
    );
    $db->insert('terms', $set);
    $affected = (boolean)$db->affected();
    if ($affected) {
    	$tid = $db->lastInsertId();
    	cache::remove('vocabulary-list');
    }
    return $tid;
  }

  /**
   * 修改分类词
   * @param array 分类词信息
   * @return boolen
   */
  public function updateTerm($tid, $post)
  {
    global $db;
    $oldTermInfo = $this->getTermInfo($tid);
    $set = array(
      'vid' => $post['vid'],
      'ptid1' => isset($post['sclass1']) ? $post['sclass1'] : 0,
      'ptid2' => isset($post['sclass2']) ? $post['sclass2'] : 0,
      'ptid3' => isset($post['sclass3']) ? $post['sclass3'] : 0,
      'pvid' => $post['pvid'],
      'name' => $post['name'],
      'description' => $post['description'],
      'path_alias' => trim(basename($post['path_alias'], '-')),
      'visible' => $post['visible'],
      'weight' => intval($post['weight']),
      'template' => $post['template'],
      'name_cn' => $post['name_cn'],
    );
    if ($post['fid']) {
      $set['fid'] = intval($post['fid']);
      $set['filepath'] = $post['filepath'];
    }
    $db->update('terms', $set, array('tid' => $tid));
    $affected = (boolean)$db->affected();
    if ($affected) {
      $set['tid'] = $tid;
      $termInfo = (object)$set;
      $vid = $termInfo->vid;
      $filter1['vid'] = $vid;
      if($termInfo->ptid3) {
      	$filter1['ptid1'] = $termInfo->ptid1;
        $filter1['ptid2'] = $termInfo->ptid2;
        $filter1['ptid3'] = $termInfo->ptid3;
        $filter1['tid'] = $termInfo->tid;
      	$filter2['directory_tid1'] = $termInfo->ptid1;
        $filter2['directory_tid2'] = $termInfo->ptid2;
        $filter2['directory_tid3'] = $termInfo->ptid3;
        $filter2['directory_tid4'] = $termInfo->tid;
      } else if($termInfo->ptid2) {
      	$filter1['ptid1'] = $termInfo->ptid1;
        $filter1['ptid2'] = $termInfo->ptid2;
        $filter1['tid'] = $termInfo->tid;
        $filter2['directory_tid1'] = $termInfo->ptid1;
        $filter2['directory_tid2'] = $termInfo->ptid2;
        $filter2['directory_tid3'] = $termInfo->tid;
      } else if ($termInfo->ptid1) {
      	$filter1['ptid1'] = $termInfo->ptid1;
        $filter1['ptid2'] = $termInfo->tid;
        $filter2['directory_tid1'] = $termInfo->ptid1;
        $filter2['directory_tid2'] = $termInfo->tid;
      } else {
        $filter1['tid'] = $termInfo->tid;
        $filter2['directory_tid1'] = $termInfo->ptid1;
      }
      if (!$oldTermInfo->ptid1) {
        $filter3['directory_tid1'] = $oldTermInfo->tid;
      } else if (!$oldTermInfo->ptid2) {
        $filter3['directory_tid1'] = $oldTermInfo->ptid1;
        $filter3['directory_tid2'] = $oldTermInfo->tid;
      } else if (!$oldTermInfo->ptid3) {
        $filter3['directory_tid1'] = $oldTermInfo->ptid1;
        $filter3['directory_tid2'] = $oldTermInfo->ptid2;
        $filter3['directory_tid3'] = $oldTermInfo->tid;
      } else {
        $filter3['directory_tid1'] = $oldTermInfo->ptid1;
        $filter3['directory_tid2'] = $oldTermInfo->ptid2;
        $filter3['directory_tid3'] = $oldTermInfo->ptid3;
        $filter3['directory_tid4'] = $oldTermInfo->tid;
      }
      
      if ($affected && isset($post['visible'])  && $oldTermInfo->visible != $post['visible'] && $post['vid'] == self::TYPE_DIRECTORY) {
        if ($post['visible'] == 1) {
          $visible = 1;
          $visible2 = 1;
          $filter2['status'] = -1;
        } else {
          $visible = 0;
          $visible2 = -1;
          $filter2['status'] = 1;
        }
        if (isset($filter1)) {

          $db->update('terms', array('visible' => $visible), $filter1);
        }
        $db->update('products', array('status' => $visible2), $filter2);
      }
      if (!($oldTermInfo->ptid1 == $termInfo->ptid1 && $oldTermInfo->ptid2 == $termInfo->ptid2 && $oldTermInfo->ptid3 == $termInfo->ptid3)) {
        $db->update('products', $filter2, $filter3);
        cache::clean();
      }
    }
    if (isset($post['termPvVolume']) && $post['termPvVolume'] !='') {
      if ($post['termPvVolume'] == 1) {
        $pvid = $post['pvid'];
        $db->update('terms', array('pvid' => $pvid), $filter1);
      }
      if ($post['termPvVolume'] == 2) {
        $pvid = 0;
        $db->update('terms', array('pvid' => $pvid), $filter1);
      }
      if ($post['termPvVolume'] == 3) {
        $pvid = $post['pvid'];
        $ptermInfo = $this->getTermInfo($tid);
        $sql = "update terms set pvid = '" . $pvid . "' WHERE ptid1 = '" . $ptermInfo->ptid1 . "' AND ptid2 = '" . $ptermInfo->ptid2 . "' AND ptid3 = '" . $ptermInfo->ptid3 . "' AND	vid = " . $ptermInfo->vid;
        $db->exec($sql);
      }
      if ($post['termPvVolume'] == 4) {
        $pvid = 0;
        $ptermInfo = $this->getTermInfo($tid);
        $sql = "update terms set pvid = '" . $pvid . "' WHERE ptid1 = '" . $ptermInfo->ptid1 . "' AND ptid2 = '" . $ptermInfo->ptid2 . "' AND ptid3 = '" . $ptermInfo->ptid3 . "' AND	vid = " . $ptermInfo->vid;
        $db->exec($sql);
      }
    }
    cache::remove('vocabulary-list');
    cache::remove('term-' . $tid);
    cache::remove('admin-term-' . $tid);
    $this->saveTermPathAlias($set['path_alias'], $tid, $post['vid']);
    return true;
  }

  public function updateTermPathAlias($tid, $pathAlias, $vid) {
    global $db;
    $db->update('terms', array('path_alias' => $pathAlias), array('tid' => $tid));
    $this->saveTermPathAlias($pathAlias, $tid, $vid);
  }

  /**
   * 保存分类词下的商品别名目录
   * @param string $dest 目标目录
   * @param int $tid 分类词ID
   */
  public function saveTermPathAlias($dest ,$tid, $vid)
  {
  	global $db;
  	if ($vid == Taxonomy_Model::TYPE_DIRECTORY || $vid == Taxonomy_Model::TYPE_TAG) {
  	  $db->delete('path_alias', array('src' => 'product/browse/' . $dest));
      $db->insert('path_alias', array('src' => 'product/browse/' . $dest, 'dest' => $dest));
  	  cache::remove('static-routers');
  	}
  }

  /**
   * 修改分类词血缘关系
   * @param int $tid 分类词ID
   * @param int $parent 分类词ID
   * @return boolen
   */
  public function updateTermHierarchy($tid, $parent)
  {
   //
  }

  /**
   * 判断分类词别名是否存在重复
   * @param int $path_alias 路径别名
   * @param int $tid 分类词ID 可选参数
   * @return boolean
   */
  public function checkPathAliasExist($path_alias, $tid = null)
  {
    global $db;
    $result = $db->query('SELECT tid FROM terms WHERE path_alias = "' . $db->escape($path_alias) . '"');
    $tidExist = $result->one();
    if (((isset($tid) && $tid != $tidExist) || !isset($tid)) && $tidExist) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * 删除分类词
   * @param int $tid 分类词ID
   * @return array
   */
  public function deleteTerm($vid, $tid)
  {
    global $db;
    $db->delete('terms', array('tid' => $tid));
    $affected = (boolean)$db->affected();
    if ($affected) {
      $this->deleteUnderTerm($tid);
    }
    //$db->delete('path_alias', array('src' => 'product/browse/' . $tid));
    cache::remove('vocabulary-list');
    cache::remove('term-' . $tid);
    cache::remove('admin-term-' . $tid);
    cache::remove('static-routers');
    return $affected;
  }

  /**
   * 删除分类词下的分类词,及血缘关系
   * @param int $tid 分类词ID
   * @return array
   */
  public function deleteUnderTerm($tid)
  {
    global $db;
    if (isset($tid) && $tid) {
      $termInfo = $this->getTermInfo($tid);
       if(!$termInfo->ptid1) {
         $column = 'ptid1';
       } else if (!$termInfo->ptid2) {
         $column = 'ptid2';
       } else if (!$termInfo->ptid3) {
         $column = 'ptid3';
       } else {
         return array();
       }
    }
    /*
    $termChilds = $this->getTermChildsByParent($tid);
    foreach ($termChilds as $key => $val)
    {
    	$db->delete('path_alias', array('src' => 'product/browse/' . $val));
    }
    */
    $db->exec('DELETE FROM terms WHERE ' . $column . ' = "' . $db->escape($tid) . '"');
    return true;
  }

  /**
   * 删除分类词的血缘关系
   * @param int $tid 分类词ID
   * @return array
   */
  private function deleteTermsHierarchy($tid)
  {
    global $db;
    //
  }

  /**
   * 获取分类词的 chirdren
   * @param int $tid 分类词ID
   * @return array
   */
  public function getTermsTidByParent($tid)
  {
    global $db;
    if (isset($tid) && $tid) {
      $termInfo = $this->getTermInfo($tid);
       if(!$termInfo->ptid1) {
         $column = 'ptid1';
       } else if (!$termInfo->ptid2) {
         $column = 'ptid2';
       } else if (!$termInfo->ptid3) {
         $column = 'ptid3';
       } else {
         return array();
       }
    }
    $result = $db->query('SELECT tid FROM terms WHERE ' . $column . ' = "' . $db->escape($tid) . '"');
    return $result->all();
  }

  /**
   * 获取分类词的 等级
   * @param int $tid 分类词ID
   * @param int parent 分类词的父ID
   * @return int 分类等级
   */
  public function getTermGrade($tid, $parent){
    global $db;
    $grade = 0;
    $termInfo = $this->getTermInfo($parent);
    if (empty($termInfo)) {
        $grade = 0;
    } else {
        $grade = 1;
    }
    if ($termInfo->ptid1) {
      $grade = 2;
    }
    if ($termInfo->ptid2) {
      $grade = 3;
    }
    if ($termInfo->ptid3) {
      $grade = 4;
    }
    $db->where('ptid1', $tid);
    $result = $db->get('terms');
    if ($result->all()) {
      $grade += 4;
    }
    $db->where('ptid2', $tid);
    $result = $db->get('terms');
    if ($result->all()) {
      $grade += 3;
    }
    $db->where('ptid3', $tid);
    $result = $db->get('terms');
    if ($result->all()) {
      $grade += 2;
    }
    return $grade;
  }

  /**
   *
   * 获取分类词别名信息
   * @param $pathAlias
   */
  public function getTermPathAlias($pathAlias, $tid = 0)
  {
    $TermInfo = $this->getTermInfoByPathAlias($pathAlias);
    if ($TermInfo && $tid != $TermInfo->tid) {
      return $pathAlias.'-' . $tid;
    } else {
      return $pathAlias;
    }
  }

  public function delTermProducts($tid, $pids)
  {
    global $db;
    $db->delete('terms_products', array('tid' => $tid, 'pid in' => $pids));
    return $db->affected();
  }
  
  public function getDirectoryTagsByTid($tid){
  	global $db;
  	$db->where("vid = ", "1");
  	$result = $db->get("terms");
  	$alltags = $result->all();
  	
  	$target = $tid % 26;
  	$directorytags = array();
  	foreach ($alltags as $tag){
  		if($target == ($tag->tid % 26) ){
  			$directorytags[] = $tag;
  		}
  	}
  	
  	return $directorytags;
  }
}
