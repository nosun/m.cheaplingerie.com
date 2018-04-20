<?php
class Sitemapxml extends Widget_Abstract
{
  private static $_changefreq = array(
    'always',
    'hourly',
    'daily',
    'weekly',
    'monthly',
    'yearly',
    'never',
  );

  public function urls()
  {
    return array(
      'pingxml',
      'pingurl',
    );
  }

  private function getSettings()
  {
    $settings = Bl_Config::get('sitemapxml.settings', array());
    $settings += array(
      'actived' => true,
      'gzipped' => true,
      'notify_google' => true,
      'notify_bing' => false,
      'notify_ask' => false,
      'content' => array(
        'home' => array(
          'included' => true,
          'changefreq' => 'daily',
          'priority' => 1,
        ),
        'term' => array(
          'included' => true,
          'changefreq' => 'daily',
          'priority' => 0.8,
        ),
        'seotag' => array(
          'included' => true,
          'changefreq' => 'daily',
          'priority' => 0.8,
        ),
        'product' => array(
          'included' => true,
          'changefreq' => 'daily',
          'priority' => 0.5,
        ),
        'comparelist' => array(
          'included' => true,
          'changefreq' => 'daily',
          'priority' => 0.8,
        ),
        'compare' => array(
          'included' => true,
          'changefreq' => 'daily',
          'priority' => 0.5,
        ),
      ),
    );
    return $settings;
  }

  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $instance->view->render('../plugins/widget/sitemapxml/edit.phtml', array(
      'settings' => $this->getSettings(),
      'changefreq' => self::$_changefreq,
    ));
  }

  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    if ($instance->isPost()) {
      $settings = $this->getSettings();
      $settings['actived'] = isset($_POST['actived']) ? true : false;
      $settings['gzipped'] = isset($_POST['gzipped']) ? true : false;
      $settings['notify_google'] = isset($_POST['notify_google']) ? true : false;
      $settings['notify_bing'] = isset($_POST['notify_bing']) ? true : false;
      $settings['notify_ask'] = isset($_POST['notify_ask']) ? true : false;
      foreach ($settings['content'] as $key => &$setting) {
        $setting['included'] = isset($_POST['content'][$key]['included']) ? true : false;
        $setting['changefreq'] = $_POST['content'][$key]['changefreq'];
        $setting['priority'] = $_POST['content'][$key]['priority'];
      }
      Bl_Config::set('sitemapxml.settings', $settings);
      Bl_Config::save();
      cache::remove('sitemapxml.content');
      setMessage('Sitemap XML setting had been saved.');
      if ($this->pingXML()) {
        setMessage('Your Sitemap has been successfully added to Google list of Sitemaps.');
      }
    }
    gotoUrl('admin/site/widgetedit/sitemapxml');
  }
  
  public function uninstall()
  {
  	global $db;
  	$db->where('key', 'sitemapxml.settings');
  	$db->where('key', 'widget.sitemapxml', 'or');
  	$db->delete('settings');
  	return $db->affected();
  }
  
  /**
   * 获取整个Sitemap地图合集
   * @return string
   */
  public function getXML1()
  {
    global $db;
    $cacheId = 'sitemapxml.content';
    header('Content-Type: text/xml');
    if ($cache = cache::get($cacheId)) {
      $xml .= $cache->data;
    } else {
      $settings = $this->getSettings();
      $xml = '';
      if ($settings['actived']) {
        $xml .= "<?xml version='1.0' encoding='UTF-8'?>";
        $xml .= "<sitemapindex xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>";
        foreach ($settings['content'] as $key => $setting) {  
          if (!$setting['included']) {
            continue;
          }
          switch ($key) {
            case 'term':
              $xml .= $this->getSitemapCeil(url('default/sitemap/term/1'));
              break;
              
            case 'seotag':
              $widgetInstance = Widget_Model::getInstance();
              $widgetInfo = $widgetInstance->getWidgetInfo('seotags');
              if ($widgetInfo && $widgetInfo->status) {
                $result = $db->query('SELECT COUNT(0) FROM widget_seotags st WHERE st.status = 1 AND st.ptag_id <> 0');
                $count = $result->one();
                $i = 0;
                for ( ; $count > 0; $count = $count - 5000) {
                  $i++;
                  $xml .= $this->getSitemapCeil(url('default/sitemap/seotag/' . $i));
                }
              }
              break;
              
            case 'product':
              $result = $db->query('SELECT COUNT(0) FROM products WHERE status = 1');
              $count = $result->one();
              $i = 0;
              for ( ; $count > 0; $count = $count - 5000) {
                $i++;
                $xml .= $this->getSitemapCeil(url('default/sitemap/product/' . $i));
              }
              break;
              
            case 'comparelist':
            	if(! Bl_Config::get('widget.productcompare')){break;}
            	$result = $db->query('SELECT COUNT(0) FROM widget_compare group by directory_tid');
              $count = $result->one();
              $i = 0;
              for ( ; $count > 0; $count = $count - 5000) {
                $i++;
                $xml .= $this->getSitemapCeil(url('default/sitemap/comparelist/' . $i));
              }
              break;
              
            case 'compare':
            	if(! Bl_Config::get('widget.productcompare')){break;}
              $result = $db->query('SELECT COUNT(0) FROM widget_compare');
              $count = $result->one();
              $i = 0;
              for ( ; $count > 0; $count = $count - 5000) {
                $i++;
                $xml .= $this->getSitemapCeil(url('default/sitemap/compare/' . $i));
              }
              break;
          }
        }
        $xml .= "</sitemapindex>";
      }
    }
    return $xml;
  }

  /**
   * 获取某一类型的地图
   * @param string $type term(分类),seotag(Tags标签),products(产品),compare(对比)
   * @param string $page 页码
   * @return string
   */
  public function getXML2($type, $page = 1)
  {
    global $db;
    $settings = $this->getSettings();
    $setting = $settings['content'][$type];
    if (!$settings['content'][$type]['included']) {
      goto404('');
    }

    switch($type) {
      case 'term' :
        $result = $db->query('SELECT t.path_alias tpa FROM terms t WHERE t.vid = 3 AND visible = 1');
        $terms = $result->all();
        foreach ($terms as $v) {
          $v->tpa = str_replace('&', '', $v->tpa);
          $v->url = 'browse/' . $v->tpa . '.html';
          $v->created = TIMESTAMP;
        }
        return $this->getXML3($terms, $type . '-' . $page, $setting);
        
      case 'seotag' :
        $limit = 'LIMIT ' . ($page - 1) * 5000 . ', 5000 ';
        $result = $db->query('SELECT st.name, st.created FROM widget_seotags st WHERE st.status = 1 AND st.ptag_id <> 0 ' . $limit);
        $tags = $result->all();
        foreach ($tags as $tag) {
          $tag->name = str_replace('&', '', $tag->name);
          $tag->url = 'search/' . $tag->name;
        }
        return $this->getXML3($tags, $type . '-' . $page, $setting);
        
      case 'product' :
        $limit = 'LIMIT ' . ($page - 1) * 5000 . ', 5000 ';
        $result = $db->query('SELECT directory_tid4, directory_tid3, directory_tid2, directory_tid1, path_alias pa, updated FROM products WHERE status = 1 ' . $limit);
        $products = $result->all();
        foreach ($products as $pa) {
          $pa->directory_tid =  $pa->directory_tid4 ? $pa->directory_tid4 : (
            $pa->directory_tid3 ? $pa->directory_tid3 : (
              $pa->directory_tid2 ? $pa->directory_tid2 : (
                $pa->directory_tid1 ? $pa->directory_tid1 : 0
              )
            )
          );
          $taxonomyInstance = Taxonomy_Model::getInstance();
          if ($pa->directory_tid) {
            $termInfo = $taxonomyInstance->getTermInfo($pa->directory_tid);
          }
          $pa->tpa = isset($termInfo->path_alias) ? $termInfo->path_alias : 'product';
          $pa->url = str_replace('&', '', $pa->tpa . '/' . $pa->pa . '.html');
          $pa->created = $pa->updated;
        }
        return $this->getXML3($products, $type . '-' . $page, $setting);
        
      case 'comparelist':
    		$limit = 'LIMIT ' . ($page - 1) * 5000 . ', 5000 ';
				$db->select('directory_tid');
				$db->distinct();
				$result = $db->get('widget_compare');
				$termsList = $result->all();
				foreach($termsList as $key => $val) {
					$val->url = 'product/comparisonlist/' . $val->directory_tid;
					 $val->created = TIMESTAMP;
				}
       return $this->getXML3($termsList, $type . '-' . $page, $setting);
       
      case 'compare':
		    $limit = 'LIMIT ' . ($page - 1) * 5000 . ', 5000 ';
		    $result = $db->query('SELECT title, pid1, pid2, pid3, pid4, timestamp FROM widget_compare ' . $limit);
		    $compareList = $result->all();
		    foreach ($compareList as $k => $v) {
		    	$pids = substr($v->title, 0, stripos($v->title, ' vs '));
		    	$pids = strtr($pids, array('&'=> '', '\'' => '', '\\' => '', '/' => '-' , ' ' => '-'));
		      if ($v->pid1) {
		        $pids .= '-' . $v->pid1;
		      }
		      if ($v->pid2) {
		        $pids .= '-' . $v->pid2;
		      }
		      if ($v->pid3) {
		        $pids .= '-' . $v->pid3;
		      }
		      if ($v->pid4) {
		        $pids .= '-' . $v->pid4;
		      }
		      $v->url = 'compare/' . $pids . '.html';
		      $v->created = $v->timestamp;
		    }
		     return $this->getXML3($compareList, $type . '-' . $page, $setting);
		     
        break; 
    }
  }
  
  /**
   * 生成子地图
   * @param object $arr 链接集
   * @param string $token 类型
   * @param array $setting sitemap时间权重等参数
   * @return string
   */
  public function getXML3($arr, $token, $setting)
  {
		$allSetting = $this->getSettings();
		$homesetting = $allSetting['content']['home'];
		
    $cacheId = 'sitemapxml.content.' . $token;
    $xml = '';
    header('Content-Type: text/xml');
    if ($cache = cache::get($cacheId)) {
      $xml .= $cache->data;
    } else {
      static $home;
      $xml .= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
      $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
      if (!isset($home)) {
        $home = $this->getUrlXML('', TIMESTAMP, $homesetting);
        $xml .= $home;
      }
      foreach ($arr as $v) {
        $xml .= $this->getUrlXML($v->url, $v->created, $setting);
      }
      $xml .= '</urlset>' . PHP_EOL;
    }
    return $xml;
  }
  
  public function getXML($gzip = false)
  {
    global $db;
    $settings = $this->getSettings();
    $xml = '';
    if ($settings['actived']) {
      $cacheId = 'sitemapxml.content';
      header('Content-Type: text/xml');
      if ($cache = cache::get($cacheId)) {
        $xml .= $cache->data;
      } else {
        $xml .= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        foreach ($settings['content'] as $key => $setting) {
          if (!$setting['included']) {
            continue;
          }
          switch ($key) {
            case 'home':
              $xml .= $this->getUrlXML('', TIMESTAMP, $setting);
              break;
              
            case 'term':
              $result = $db->query('SELECT t.path_alias tpa FROM terms t WHERE t.vid = 3 AND visible = 1');
              $terms = $result->column();
              foreach ($terms as $tpa) {
                $tpa = str_replace('&', '', $tpa);
                $xml .= $this->getUrlXML('browse/' . $tpa . '.html', TIMESTAMP, $setting);
              }
              break;
              
            case 'seotag':
              $widgetInstance = Widget_Model::getInstance();
              $widgetInfo = $widgetInstance->getWidgetInfo('seotags');
              if ($widgetInfo && $widgetInfo->status) {
                $result = $db->query('SELECT st.name, st.created FROM widget_seotags st WHERE st.status = 1 AND st.ptag_id <> 0');
                $tags = $result->all();
                foreach ($tags as $tag) {
                  $tag->name = str_replace('&', '', $tag->name);
                  $xml .= $this->getUrlXML('search/' . $tag->name, $tag->created, $setting);
                }
              }
              break;
              
            case 'product':
              $result = $db->query('SELECT directory_tid4, directory_tid3, directory_tid2, directory_tid1, path_alias pa, updated FROM products WHERE status = 1');
              $products = $result->all();
              foreach ($products as $pa) {
                $pa->directory_tid =  $pa->directory_tid4 ? $pa->directory_tid4 : (
                  $pa->directory_tid3 ? $pa->directory_tid3 : (
                    $pa->directory_tid2 ? $pa->directory_tid2 : (
                      $pa->directory_tid1 ? $pa->directory_tid1 : 0
                    )
                  )
                );
                $taxonomyInstance = Taxonomy_Model::getInstance();
                if ($pa->directory_tid) {
                  $termInfo = $taxonomyInstance->getTermInfo($pa->directory_tid);
                }
                $pa->tpa = isset($termInfo->path_alias) ? $termInfo->path_alias : 'product';
                $purl = str_replace('&', '', $pa->tpa . '/' . $pa->pa . '.html');
                $xml .= $this->getUrlXML($purl, $pa->updated, $setting);
              }
              break;
              
           case 'comparelist':
			    		$limit = 'LIMIT ' . ($page - 1) * 5000 . ', 5000 ';
							$db->select('directory_tid');
							$db->distinct();
							$result = $db->get('widget_compare');
							$termsList = $result->all();
							foreach($termsList as $key => $val) {
								$val->url = 'product/comparisonlist/' . $val->directory_tid;
								 $val->created = TIMESTAMP;
								 $xml .= $this->getUrlXML($val->url, $val->created, $setting);
							}
							break;
							
            case 'compare':
					    $limit = 'LIMIT ' . ($page - 1) * 5000 . ', 5000 ';
					    $result = $db->query('SELECT title, pid1, pid2, pid3, pid4, timestamp FROM widget_compare ' . $limit);
					    $compareList = $result->all();
					    foreach ($compareList as $k => $v) {
					    	$pids = substr($v->title, 0, stripos($v->title, ' vs '));
					    	$pids = strtr($pids, array('&'=> '', '\'' => '', '\\' => '', '/' => '-' , ' ' => '-'));
					      if ($v->pid1) {
					        $pids .= '-' . $v->pid1;
					      }
					      if ($v->pid2) {
					        $pids .= '-' . $v->pid2;
					      }
					      if ($v->pid3) {
					        $pids .= '-' . $v->pid3;
					      }
					      if ($v->pid4) {
					        $pids .= '-' . $v->pid4;
					      }
					      $v->url = 'compare/' . $pids . '.html';
					      $v->created = $v->timestamp;
					      $xml .= $this->getUrlXML( $v->url, $v->timestamp, $setting);
					    }
					    break;
          }
        }
        $xml .= '</urlset>' . PHP_EOL;
        cache::save($cacheId, $xml, 36000);
      }
    }
    return $xml;
  }

  public function _pingxml()
  {
    $this->pingXML();
  }

  public function _pingurl(Bl_Controller $instance, $url)
  {
    $url = base64_decode($url);
    $this->pingUrl($url);
  }

  public function pingXML( $url = NULL)
  {
    global $domainUrl;

    if(empty($url)) {$url = url('sitemap.xml');}
    $url = 'www.google.com/webmasters/tools/ping?sitemap=' . $url;
    Bl_Core::loadLibrary('curl');
    $curl = new Curl();
    $curl->initCurl();
    $curl->setCurlOption(array(
      'url' => $url,
      'nobody' => true,
    ));
    $curl->execCurl();
    $httpcode = $curl->getCurlCode();
    $curl->closeCurl();

    return $httpcode == '200';
  }

  public function pingUrl($url)
  {
    global $domainUrl;
    $url = 'blogsearch.google.com/ping?url=' . url($url);
    Bl_Core::loadLibrary('curl');
    $curl = new Curl();
    $curl->initCurl();
    $curl->setCurlOption(array(
      'url' => $url,
      'nobody' => true,
    ));
    $curl->execCurl();
    $httpcode = $curl->getCurlCode();
    $curl->closeCurl();
    return $httpcode == '200';
  }
  
  public function getSitemapCeil($url)
  {
    $xml = "<sitemap>". PHP_EOL;
    $xml .= "<loc>" . $url . "</loc>". PHP_EOL;
    $xml .= "<lastmod>" . date('Y-m-d', TIMESTAMP) . "</lastmod>". PHP_EOL;
    $xml .= "</sitemap>". PHP_EOL;
    return $xml;
  }

  private function getUrlXML($url, $timestamp, $setting = null)
  {
    global $domainUrl;
    $url = strtr($url, array(
      ' ' => '-',
    ));
    $xml = '<url>' . PHP_EOL;
    $xml .= '  <loc>' . url($url) . '</loc>' . PHP_EOL;
    $xml .= '  <lastmod>' . date('Y-m-d', $timestamp) . '</lastmod>' . PHP_EOL;
    $xml .= '  <changefreq>' . $setting['changefreq'] . '</changefreq>' . PHP_EOL;
    $xml .= '  <priority>' . $setting['priority'] . '</priority>' . PHP_EOL;
    $xml .= '</url>' . PHP_EOL;
    return $xml;
  }
}