<?php
class PageVariable_Model extends Bl_Model
{
  /**
   * @return PageVariable_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

 /**
  *
  * 更新页面元素信息
  * @param int $pvid
  * @param array $post
  */
  public function updatePageVariables($pvid, $post)
  {
    global $db;
    if (isset($post['key']) && $post['key']) {
      $pvInfo = $this->getPageVariableByKey($post['key']);
      return isset($pvInfo->pvid) ? $pvInfo->pvid : 0;
    } else {
      $pvInfo = $this->getPageVariable($pvid);
      if (isset($pvInfo) && $pvInfo && $pvInfo->key == '') {
        $set = array(
        'title' => isset($post['title']) ? $post['title'] : null,
        'meta_keywords' => isset($post['meta_keywords']) ? $post['meta_keywords'] : null,
        'meta_description' => isset($post['meta_description']) ? $post['meta_description'] : null,
        'var1' => isset($post['var1']) ? $post['var1'] : null,
        'var2' => isset($post['var2']) ? $post['var2'] : null,
        'var3' => isset($post['var3']) ? $post['var3'] : null,
        'var4' => isset($post['var4']) ? $post['var4'] : null,
        'var5' => isset($post['var5']) ? $post['var5'] : null,
        'var6' => isset($post['var6']) ? $post['var6'] : null,
      );
      $set['key'] = isset($post['key']) ? $post['key'] : '';

      cache::remove('pagevariable-' . $pvid);
      cache::remove('pagevariable_theme');
      if (array_filter($set, "Common_Model::filterArray")) {
        $db->update('page_variables', $set, array('pvid' => $pvid));
      } else {
        $db->delete('page_variables', array('pvid' => $pvid));
        $pvid = 0;
      }

    } else {
      $pvid = $this->insertPageVariables($post);
    }
      return $pvid;
    }
  }

  public function updatePageVariablesByKey($key, $post)
  {
    global $db;
    $set = array(
      'title' => $post['title'],
      'meta_keywords' => $post['meta_keywords'],
      'meta_description' => $post['meta_description'],
      'var1' => $post['var1'],
      'var2' => $post['var2'],
      'var3' => $post['var3'],
      'var4' => $post['var4'],
      'var5' => $post['var5'],
      'var6' => $post['var6'],
    );
    cache::remove('pagevariable-' . $pvid);
    cache::remove('pagevariable_theme');
    if (array_filter($set, "Common_Model::filterArray")) {
      $db->update('page_variables', $set, array('key' => $key));
    }
    return $db->affected();
  }

 /**
  *
  * 新增页面元素信息
  * @param $pvid
  * @param $post
  */
  public function insertPageVariables($post)
  {
    global $db;
    $set = array(
      'key' => isset($post['key']) ? $post['key'] : null,
      'title' => $post['title'],
      'meta_keywords' => $post['meta_keywords'],
      'meta_description' => $post['meta_description'],
      'var1' => $post['var1'],
      'var2' => $post['var2'],
      'var3' => $post['var3'],
      'var4' => $post['var4'],
      'var5' => $post['var5'],
      'var6' => $post['var6'],
    );
    if (array_filter($set, "Common_Model::filterArray")) {
      $db->insert('page_variables', $set);
      cache::remove('pagevariable_theme');
      return $db->lastInsertId();
    }
    return 0;
  }

  /**
   *
   * 获取页面元素信息
   * @param int $pvid
   */
  public function selectPageVariables($pvid, $type = null, $data = null)
  {
    global $db;
    static $list = array();
    if (!isset($list[$pvid])) {
      $cacheId = 'pagevariable-' . $pvid;
      if ($cache = cache::get($cacheId)) {
        $list[$pvid] = $cache->data;
      } else {
        if (isset($pvid) && $pvid) {
          $pageinfo = $this->getPageVariable($pvid);
        } else {
          $pageinfo = $this->getPageVariableByKey($type);
        }
        $pageinfo = $this->getPageVariableRandomInfo($pageinfo);
        if (isset($pageinfo->key) && $pageinfo->key && isset($type) && isset($data)) {
          if ($type == 'product' || $type == 'productcomment' || $type == 'productimages' || $type == 'comparisonview') {
          	$replace = array(
              'directory1' => isset($data->directory[0]->name) ? $data->directory[0]->name : null,
              'directory2' => isset($data->directory[1]->name) ? $data->directory[1]->name : null,
              'directory3' => isset($data->directory[2]->name) ? $data->directory[2]->name : null,
              'price' => isset($data->price) ? $data->price : null,
            );
            foreach ($pageinfo as $k => $v) {
              foreach ($replace as $k2 => $v2) {
                $pageinfo->$k = str_replace('{'.$type.'.' . $k2 . '}' , $v2, $pageinfo->$k);
              }
            }
          }
          $list[$pvid] = $this->ReplaceThemeVariables($type, $pageinfo, $data);
          $list[$pvid] = $this->replaceSiteVariables($list[$pvid]);
        } else {
          $list[$pvid] = $pageinfo;
          cache::save($cacheId,  $list[$pvid]);
        }
      }
    }
    return $list[$pvid];
  }

  public function deletePageVariable($pvid)
  {
    global $db;
    $db->delete('page_variables', array('pvid' => $pvid));
    cache::remove('pagevariable-' . $pvid);
    cache::remove('pagevariable_theme');
    return $db->affected();
  }

  public function getPageVariable($pvid)
  {
    global $db;
    $result = $db->query('SELECT * FROM page_variables WHERE pvid = "' . $db->escape($pvid) . '"');
    return $result->row();
  }

  public function getPageVariablesThemeList()
  {
    global $db;
    static $list;
    if (!isset($list)) {
      $cacheId = 'pagevariable_theme';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        $result = $db->query('SELECT * FROM page_variables WHERE `key` <> ""');
        $list = $result->allWithKey('key');
        foreach ($list as $k => $v) {
          $list[$k] = $this->getPageVariableRandomInfo($v);
        }
        cache::save($cacheId, $list);
      }
    }
    return $list;
  }

  public function getPageVariableByKey($key)
  {
    $list = $this->getPageVariablesThemeList();
    return isset($list[$key]) ? $list[$key] : false;
  }

  public function ReplaceThemeVariables($type, $pageinfo, $data)
  {
    foreach ($pageinfo as $k => $v) {
      if ($v) {
        preg_match_all('/{'.$type.'.(\w*)}/', $v, $regs);
        if (isset($regs[1]) && $regs[1]) {
          foreach ($regs[1] as $k2 => $v2) {
            if (isset($data->$v2)) {
              $pageinfo->$k = str_replace($regs[0][$k2], $data->$v2, $pageinfo->$k);
            }
          }
        }
      }
    }
    return $pageinfo;
  }

  public function replaceSiteVariables($pageinfo){
  	if($pageinfo){
	  	foreach ($pageinfo as $k => $v) {
	      if ($v) {
	      	$siteInfo = Bl_Config::get('siteInfo', array());
	      	preg_match_all('/{site.(\w*)}/', $v, $siteRegs);
	      	if (isset($siteRegs[1]) && $siteRegs[1]) {
	      		foreach ($siteRegs[1] as $k3 => $v3) {
	      			if (isset($siteInfo[$v3])) {
	      				$pageinfo->$k = str_replace($siteRegs[0][$k3], $siteInfo[$v3], $pageinfo->$k);
	      			}
	      		}
	      	}
	      }
	  	}
  	}
  	return $pageinfo;
  }
  
  
  public function getPageVariableRandomInfo($pageinfo)
  {
    if (isset($pageinfo) && !empty($pageinfo)) {
      foreach ($pageinfo as $k => $v) {
        if ($v) {
          preg_match_all('/{\[(.*?)\]}/', $v, $regs);
          if (isset($regs[0]) && is_array($regs[0])) {
            foreach ($regs[0] as $k2 => $v2) {
              $replacearr = explode('||', $regs[1][$k2]);
              $num = count($replacearr);
              $uri = Bl_Core::getUri();
              $urimd5 = md5($uri);
              $strlen = ord($urimd5[0]);
              $index = $strlen%$num;
              $replace = $replacearr[$index];
              $pageinfo->$k = str_replace($v2, $replace, $pageinfo->$k);
            }
          }
        }
      }
    }
    return $pageinfo;
  }
}