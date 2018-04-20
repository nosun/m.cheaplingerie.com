<?php
class Site_Model extends Bl_Model
{
  /**
   * @return Site_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取模板列表
   * @param boolean $reset 是否重新读取
   * @return array
   */
  public function getTemplatesList($reset = false)
  {
    static $list = null;
    if (!isset($list) || $reset) {
      $list = array();
      if ($dh = opendir(TPLPATH)) {
        while (false !== ($file = readdir($dh))) {
          if ($file[0] == '.') {
            continue;
          }
          if (is_dir(TPLPATH . '/' . $file) && is_file(TPLPATH . '/' . $file . '/' . $file . '.info')) {
            $info = parse_ini_file(TPLPATH . '/' . $file . '/' . $file . '.info');
            if (!empty($info) && isset($info['name'])) {
              $info['template'] = $file;
              if (!isset($info['engine']) || !in_array($info['engine'], array('php'))) {
                $info['engine'] = 'php';
              }
              $info['screenshot'] = is_file(TPLPATH . '/' . $file . '/screenshot.png');
              $list[$file] = (object)$info;
            }
          }
        }
        closedir($dh);
      }
    }
    return $list;
  }

  /**
   * 获取模板信息
   * @param string $template 模板标识
   * @return array
   */
  public function getTemplateInfo($template)
  {
    $list = $this->getTemplatesList();
    return isset($list[$template]) ? $list[$template] : null;
  }

  /**
   * 获取模板文件列表
   * @param string $template 模板名称
   * @return array
   */
  public function getTemplateFiles($template)
  {
    return $this->getTemplateFilesIteration(TPLPATH . '/' . $template . '/views');
  }

  /**
   * 获取站点模板文件列表
   * @return array
   */
  public function getSiteTemplateFiles()
  {
    return $this->getTemplateFilesIteration(SITEPATH . '/views');
  }

  /**
   * 迭代获取模板文件列表
   * @param string $path 模板文件目录
   * @return array
   */
  public function getTemplateFilesIteration($path)
  {
    $files = array();
    if ($dh = @opendir($path)) {
      while (false !== ($file = readdir($dh))) {
        if ($file[0] == '.') {
          continue;
        }
        if (is_dir($path . '/' . $file)) {
          $files[$file] = $this->getTemplateFilesIteration($path . '/' . $file);
        } else if (getFileExtname($file) == 'phtml') {
          $files[] = $file;
        }
      }
      closedir($dh);
    }
    return $files;
  }

  /**
   * 获取模板文件状态
   * @param string $template 模板文件名称
   * @return array
   */
  public function getTemplateFilesStatus($template)
  {
    $templateFiles = $this->getTemplateFiles($template);
    $siteFiles = $this->getSiteTemplateFiles();
    $files = array();
    foreach ($templateFiles as $dir => $file) {
      if (is_array($file)) {
        foreach ($file as $f) {
          if (!is_array($f)) {
            $file = $dir . '/' . $f;
            if (isset($siteFiles[$dir]) && false !== ($k = array_search($f, $siteFiles[$dir]))) {
              $status = 1;
              unset($siteFiles[$dir][$k]);
            } else {
              $status = 0;
            }
            $files[$file] = $status;
          }
        }
      } else {
        if (false !== ($k = array_search($file, $siteFiles))) {
          $status = 1;
          unset($siteFiles[$k]);
        } else {
          $status = 0;
        }
        $files[$file] = $status;
      }
    }
    foreach ($siteFiles as $dir => $file) {
      if (is_array($file)) {
        foreach ($file as $f) {
          $files[$f] = 2;
        }
      } else {
        $files[$file] = 2;
      }
    }
    ksort($files);
    return $files;
  }

  /**
   * 获取模板文件内容
   * @param string $template 模板名
   * @param string $file 模板文件名
   * @param boolean $loadDefault 是否载入默认
   */
  public function getTemplateFileContent($template, $file, $loadDefault = false)
  {
    $siteTemplatePath = SITEPATH . '/views/' . $file;
    $templatePath = TPLPATH . '/' . $template . '/views/' . $file;
    if (!$loadDefault && is_file($siteTemplatePath)) {
      return array(
        'in_site' => true,
        'content' => file_get_contents($siteTemplatePath),
      );
    } else if (is_file($templatePath)) {
      return array(
        'in_site' => false,
        'content' => file_get_contents($templatePath),
      );
    } else {
      return false;
    }
  }

  /**
   * 保存你模板文件内容
   * @param string $template 模板名
   * @param string $file 模板文件名
   * @param string $content 模板内容
   */
  public function saveTemplateFileContent($template, $file, $content)
  {
    $siteTemplatePath = HOSTNAME . '/views/' . $file;
    $sitePPath = SITESPATH . '/' . $siteTemplatePath;
    $content = strtr($content, array("\r\n" => "\n"));
    if (!is_dir(dirname($sitePPath))) {
      makedir(dirname($siteTemplatePath), SITESPATH);
    }
    file_put_contents($sitePPath, $content);
  }

  /**
   * 删除模板文件
   * @param string $file 模板文件名
   */
  public function deleteTemplateFile($file)
  {
    $siteTemplatePath = SITEPATH . '/views/' . $file;
    if (is_file($siteTemplatePath)) {
      unlink($siteTemplatePath);
    }
  }

  /**
   *
   * 新增别名记录
   * @param string $src 真实地址
   * @param string $dest 别名
   */
  public function insertPathAlias($src, $dest)
  {
    global $db;
    $db->insert('path_alias', array('src' => $src, 'dest' => $dest)) ;
    return $db->lastInsertId();
  }

  /**
   *
   * 修改别名记录
   * @param string $src 真实地址
   * @param string $dest 别名
   */
  public function updatePathAlias($src, $dest)
  {
    global $db;
    $db->update('path_alias', array('dest' => $dest), array('src' => $src)) ;
    return $db->affected();
  }

  /**
   *
   * 保存别名信息
   * @param string $src 真实地址
   * @param string $dest 别名
   */
  public function savePathAlias($src, $dest)
  {
  	global $db;
  	$db->where('src', $src);
  	$result = $db->get('path_alias');
  	$pathInfo = $result->one();
  	if ($pathInfo) {
  		return $this->updatePathAlias($src, $dest);
  	} else {
  		return $this->insertPathAlias($src, $dest);
  	}
  	cache::remove('static-routers');
  }

  /**
   *
   * 获取不重复的别名记录
   * @param string $src 真实地址
   * @param string $dest 别名
   */
  public function getPathAliasNorepeat($src, $dest)
  {
  	global $db;
  	$db->where('src', $dest);
    $result = $db->get('path_alias');
    $pathInfo = $result->one();
  	if ($pathInfo && $pathInfo->dest != $dest) {
  		$arr = explode('-', $dest);
  		if (isset($arr[1])){
  			$i = $arr[1] + 1;
  		} else {
  			$i = 1;
  		}
  		return $dest.'-'.$i;
  	} else {
  		return $dest;
  	}
  }
  
  /**
   * 删除别名记录
   * @param string $src 真实地址
   */
  public function deletePathAlias($src)
  {
  	global $db;
  	$db->delete('path_alias', array('src' => $src));
  	cache::remove('static-routers');
  }

  /**
   *
   * 获取货币列表
   */
  public function getCurrenciesList($visible = null)
  {
  	global $db;
  	static $array = array();
  	$key = intval($visible);
  	if (!isset($list[$key])) {
  	  $cacheId = 'currency';
  	  if ($cache = cache::get($cacheId)) {
  	    $list[0] = $cache->data;
  	  } else {
  	    $result = $db->query('SELECT * FROM currency');
        $defaultCurrency = Bl_Config::get('currency', 0);
  	    $rows = $result->allWithKey('name');
  	    foreach ($rows as $row) {
  	      $row->isdefault = ($row->name == $defaultCurrency);
  	    }
  	    $list[0] = $rows;
  	    cache::save($cacheId, $rows);
  	  }
    	if (isset($visible) && $visible) {
    	  $list[1] = array();
    	  foreach ($list[0] as $row) {
    	    if ($row->visible) {
    	      $list[1][$row->name] = $row;
    	    }
    	  }
    	}
  	}
    return $list[$key];
  }

 /**
  *
  * 获取指定货币信息
  * @param $name
  */
  public function getCurrencyInfo($name)
  {
    global $db;
    static $list = array();
    if (!isset($list[$name])) {
	    $db->where('name', $name);
	    $result = $db->get('currency');
	    $array = $result->row();
	    $currency = Bl_Config::get('currency', 0);
	    if ($array) {
		    if ($array->name == $currency) {
		    	$array->default = 1;
		    } else {
		    	$array->default = 0;
		    }
	    }
	    $list[$name] = $array;
    }
    return $list[$name];
  }

  /**
   *
   * 更新货币信息
   * @param unknown_type $name
   * @param unknown_type $post
   */
  public function updateCurrency($name, $post)
  {
  	global $db;
  	$set = array(
  	  'fullname' => isset($post['fullname']) ? $post['fullname'] : null,
  	  'symbol' => isset($post['symbol']) ? $post['symbol'] : null,
  	  'exchange' => isset($post['exchange']) ? $post['exchange'] : null,
  	  'visible' => isset($post['visible']) ? $post['visible'] : null,
  	);
  	$db->update('currency', $set, array('name' => $name));
  	$status = $db->affected();
  	if ($post['default']) {
  	  Bl_Config::set('currency',$name);
      Bl_Config::save();
  	}
  	if (!$status) {
  		$status = $db->affected();
  	}
  	cache::remove('currency');
  	return $status;
  }

  /**
   *
   * 新增货币信息
   * @param unknown_type $post
   */
  public function insertCurrency($post)
  {
    global $db;
    $set = array(
      'name' => isset($post['name']) ? $post['name'] : null,
      'fullname' => isset($post['fullname']) ? $post['fullname'] : null,
      'exchange' => isset($post['exchange']) ? $post['exchange'] : null,
      'symbol' => isset($post['symbol']) ? $post['symbol'] : null,
      'visible' => isset($post['visible']) ? $post['visible'] : null,
    );
    $db->insert('currency', $set);
    if ($post['default'] || is_null(Bl_Config::get('currency'))) {
      Bl_Config::set('currency', $post['name']);
      Bl_Config::save();
    }
    cache::remove('currency');
    return $db->lastInsertId();
  }

  /**
   *
   * 删除货币
   * @param unknown_type $name
   */
  public function deleteCurrency($name)
  {
  	global $db;
  	$db->delete('currency', array('name' => $name));
  	cache::remove('currency');
  	return $db->affected();
  }

  public function getadphotoList($page, $pageRows)
  {
  	global $db;
  	$db->orderby('aid DESC');
  	$db->limitPage($pageRows, $page);
  	$result = $db->get('advertisement');
  	return $result->allWithKey('aid');
  }

  public function getadphotoCount()
  {
    global $db;
    $db->select('count(*) num');
    $result = $db->get('advertisement');
    return $result->one();
  }

  public function getadphotoInfo($aid, $tid = 0)
  {
  	global $db;
  	$db->where('aid', $aid);
  	if ($tid) {
  	  $db->where('tid', $tid);
  	}
  	$result = $db->get('advertisement');
  	return $result->row();
  }

  public function getadphotoInfoByScriptId($script_id, $tid = 0)
  {
    global $db;
    $db->where('script_id', $script_id);
    if ($tid) {
      $db->where('tid', $tid);
    }
    $db->orderby('rand()');
    $db->limit(1);
    $result = $db->get('advertisement');
    return $result->row();
  }

  public function updateadphoto($aid, $post)
  {
  	global $db;
  	$set = array(
  	  'script_id' => $post['script_id'],
  	  'tid' => $post['tid'],
  	  'name' => $post['name'],
	  	'width' => $post['width'],
	  	'height' => $post['height'],
	  	'type' => $post['type'],
  	  'fid' => intval($post['fid']),
	  	'url' => $post['url'],
  	  'visible' => $post['visible'],
  	);
  	if ($post['filepath']) {
  		$set['filepath'] = $post['filepath'];
  	}
  	return $db->update('advertisement', $set, array('aid' => $aid));
  }

  public function insertadphoto($post)
  {
  	global $db;
    $set = array(
      'script_id' => $post['script_id'],
      'tid' => $post['tid'],
      'name' => $post['name'],
      'width' => $post['width'],
      'height' => $post['height'],
      'type' => $post['type'],
      'fid' => intval($post['fid']),
      'url' => $post['url'],
      'filepath' => $post['filepath'],
      'visible' => $post['visible'],
    );
    $db->insert('advertisement', $set);
    return $db->lastInsertId();
  }

  public function deleteadphoto($aid)
  {
  	global $db;
  	$db->delete('advertisement', array('aid' => $aid));
  	return $db->affected();
  }


  public function getcarouselphotoList($page, $pageRows, $filterInvisible=true)
  {
    global $db;
    $db->orderby('sid DESC');
    if($filterInvisible){
    	$db->where('visible', '1');
     	$db->where('isMobile', '1');
    }
    $db->limitPage($pageRows, $page);
    $result = $db->get('slide');
    return $result->allWithKey('sid');
  }

  public function getcarouselphotoCount($filterInvisible=true)
  {
    global $db;
    $db->select('count(*) num');
    if($filterInvisible){
    	$db->where('visible', '1');
     	$db->where('isMobile', '1');
    }
    $result = $db->get('slide');
    return $result->one();
  }

  public function getcarouselphotoInfo ($sid)
  {
    global $db;
    $db->where('sid', $sid);
    $result = $db->get('slide');
    return $result->row();
  }
  public function updatecarouselphoto($sid, $post)
  {
    global $db;
    $set = array(
      'fid' => intval($post['fid']),
      'title' => $post['title'],
      'description' => $post['description'],
      'visible' => $post['visible'],
      'url' => $post['url'],
      'isMobile' => $post['ismobile'],
    );
    if ($post['filepath']) {
      $set['filepath'] = $post['filepath'];
    }
    return $db->update('slide', $set, array('sid' => $sid));
  }

  public function insertcarouselphoto($post)
  {
    global $db;
    $set = array(
      'fid' => intval($post['fid']),
      'title' => $post['title'],
      'description' => $post['description'],
      'filepath' => $post['filepath'],
      'url' => $post['url'],
      'visible' => $post['visible'],
      'isMobile' => $post['ismobile'],
    );
    return $db->insert('slide', $set);
  }

  public function deletecarouselphoto($sid)
  {
    global $db;
    $db->delete('slide', array('sid' => $sid));
    return $db->affected();
  }

  /**
   * 获取国家列表
   * @return array
   */
  public function getCountries()
  {
    global $db;
    static $list;
    if (!isset($list)) {
      $cacheId = 'countries-list';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        $result = $db->query('SELECT DISTINCT cid, country_name FROM `area` ORDER BY `weight`, cid');
        $list = $result->columnWithKey('cid', 'country_name');
        cache::save($cacheId, $list);
      }
    }
    return $list;
  }

  /**
   * 获取地区列表
   * @param int $cid 国家ID
   * @return array
   */
  public function getProvinces($cids)
  {
    global $db;
    static $list;
    if (!isset($list)) {
      $cacheId = 'provinces-list';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        $result = $db->query('SELECT cid, pid, province_name FROM `area` WHERE pid > 0 ORDER BY `weight`, pid');
        $provinces = $result->all();
        $list = array();
        foreach ($provinces as $province) {
          $cid = $province->cid;
          if (!isset($list[$cid])) {
            $list[$cid] = array();
          }
          $list[$cid][$province->pid] = $province->province_name;
        }
        cache::save($cacheId, $list);
      }
    }
    return isset($list[$cids]) ? $list[$cids] : array();
  }


  public function getCountryProvincesNames($cid, $pid = null)
  {
  	global $db;
  	$db->where('cid', $cid);
  	if ($pid) {
  		$db->where('pid', $pid);
  	}
  	$result = $db->get('area');
  	$arr = $result->row();
  	return array($arr->country_name, $arr->province_name);
  }

  /**
   * 获取系统更新的版本列表
   * @param $currentVersion int 当前版本号
   * @return array
   */
  public function getUpdateVersions($currentVersion = 0)
  {
    static $updateVersions;
    if (!isset($updateVersions)) {
      $updateVersions = array();
      $updateFilename = SITESPATH . '/default/update.php';
      if (is_file($updateFilename)) {
        include_once $updateFilename;
        while (function_exists('update_' . sprintf('%04d', ++$currentVersion))) {
          $updateVersions[] = $currentVersion;
        }
      }
    }
    return $updateVersions;
  }

  /**
   * 更新系统到最新版本
   * @param $currentVersion int 当前版本号
   */
  public function runUpdate($currentVersion = 0)
  {
    $updateVersions = $this->getUpdateVersions($currentVersion);
    $version = $currentVersion;
    foreach ($updateVersions as $version) {
      $functionName = 'update_' . sprintf('%04d', $version);
      if (function_exists($functionName)) {
        call_user_func($functionName);
      }
    }
    return $version;
  }
}
