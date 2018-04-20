<?php
final class Bl_General_Exception extends Exception
{
  public function __construct($message, $code = 0)
  {
    parent::__construct($message, $code);
    if (Bl_Config::get('log.error', true)) {
      log::save('error', $message, $this);
    }
  }
}

final class Bl_403_Exception extends Exception
{
  public function __construct($message, $code = 0)
  {
    header('HTTP/1.1 403 Forbidden');
    parent::__construct($message, $code);
    if (Bl_Config::get('log.error', true)) {
      log::save('403', $message, $this);
    }
  }
}

final class Bl_404_Exception extends Exception
{
  private $_uri;
  private $_router;

  public function __construct($message, $code = 0)
  {
    header('HTTP/1.1 404 Not Found');
    parent::__construct($message, $code);
    $this->_uri = Bl_Core::getUri();
    $this->_router = Bl_Core::getRouter();
    if (class_exists('log') && Bl_Config::get('log.404', true)) {
//       log::save('404', $message, $this);
    }
  }

  public function getUri()
  {
    return $this->_uri;
  }

  public function getRouter()
  {
    return $this->_router;
  }
}

function goto403($message = 'Access Denied.')
{
  throw new Bl_403_Exception($message);
}

function goto404($message = '')
{
  throw new Bl_404_Exception($message);
}

function gotoUrl($path, $httpCode = 302)
{
  if (strcasecmp('http://', $path) != 0 && strcasecmp('https://', $path) != 0) {
    $path = url($path, false);
  }
  header('Location: ' . $path, true, $httpCode);
  exit;
}

/**
 * 
 * 跳转到原来的页面。
 * @param unknown_type $defaultPath
 * @param unknown_type $httpCode
 */
function gotoBack($defaultPath = '', $httpCode = 302)
{
  if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != '') {
    header('Location: ' . $_SERVER['HTTP_REFERER'], true, $httpCode);
    exit;
  } else {
    gotoUrl($defaultPath, $httpCode);
  }
}

/**
 * 
 * 生成随机字符串
 * @param unknown_type $len
 * @param unknown_type $type
 * @throws Bl_403_Exception
 * @throws Bl_404_Exception
 */
function randomString($len, $type = null)
{
  $randstring = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  if (isset($type)) {
    if ($type == 10 || $type == 16) {
      $randstring = substr($randstring, 0, $type);
    } else if ($type == 'a') {
      $randstring = substr($randstring, 10);
    }
  }
  $length = strlen($randstring) - 1;
  $result = '';
  for ($i = 0; $i < $len; ++ $i) {
    $result .= $randstring[mt_rand(0, $length)];
  }
  return $result;
}

/**
 * 
 * 获得远端ip地址
 * @param unknown_type $returnLong
 */
function ipAddress($returnLong = false)
{
  $ipAddress = null;
  $ipAddressLong;
  if (!empty($ipAddress)) {
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ipAddress = $_SERVER['REMOTE_ADDR'];
    }
  }
  if ($returnLong) {
    if (!isset($ipAddressLong)) {
      $ipAddressLong = ip2long($ipAddress);
    }
    return $ipAddressLong;
  } else {
    return $ipAddress;
  }
}

/**
 * 
 * 检查ip地址
 * @param unknown_type $ip
 * @param unknown_type $cidr
 * @throws Bl_403_Exception
 * @throws Bl_404_Exception
 */
function ipCheck($ip, $cidr)
{
  static $list = array();
  if (!isset($list[$ip])) {
    $list[$ip] = ip2long($ip);
  }
  $parts = explode('/', $cidr);
  if (isset($parts[1])) {
    $parts[0] .= $parts[1] > 16 ? '.0' : '.0.0';
    $ipMask = ~((1 << (32 - $parts[1])) - 1);
    return (($list[$ip] & $ipMask) == ip2long($parts[0]));
  } else {
    return !strcmp($ip, $parts[0]);
  }
}

/**
 * 
 * 获得匿名用户
 * @param unknown_type $sid
 * @throws Bl_403_Exception
 * @throws Bl_404_Exception
 */
function anonymousUser($sid)
{
  $user = new stdClass();
  $userInstance = User_Model::getInstance();
  $user->uid = 0;
  $user->name = 'Anonymous';
  $user->sid = $sid;
  $user->ip = ipAddress();
  $user->rid = User_Model::RANK_MEMBER;
  $user->roles = array(
    User_Model::ROLE_ANONYMOUS_USER,
  );
  $user->permissions = $userInstance->getRolePermissions(User_Model::ROLE_ANONYMOUS_USER);
  return $user;
}

function timer()
{
  static $timer = null;
  if (!isset($timer)) {
    $timer = microtime(true);
    return 0;
  } else {
    $startTimer = $timer;
    $timer = microtime(true);
    return $timer - $startTimer;
  }
}

function getFileExtname($filename)
{
  if ($dotpos = strrpos($filename, '.')) {
    return substr($filename, $dotpos + 1);
  } else {
    return '';
  }
}

function makedir($path, $mode = 0706, $root = DOCROOT)
{
  $path = explode('/', trim($path, '/'));
  while ($dir = array_shift($path)) {
    $root .= '/' . $dir;
    if (!is_dir($root)) {
      mkdir($root, $mode);
    }
  }
}

function hasFunction($func)
{
  return function_exists('hook_' . $func);
}

function callFunction($func)
{
  $args = func_get_args();
  array_shift($args);
  if (function_exists('hook_' . $func)) {
    return call_user_func_array('hook_' . $func, $args);
  } else if (function_exists($func)) {
    return call_user_func_array($func, $args);
  }
}

function widgetCallFunctionAll($func)
{
  $args = func_get_args();
  array_shift($args);
  $widgetInstance = Widget_Model::getInstance();
  $list = $widgetInstance->getWidgetsList();
  foreach ($list as $pk => $widget) {
    if (!$widget->status) {
      continue;
    }
    $instance = $widgetInstance->getWidgetInstance($pk);
    if (method_exists($instance, 'hook_' . $func)) {
      call_user_func_array(array($instance, 'hook_' . $func), $args);
    }
  }
}

function widgetHasFunction($widget, $func)
{
  $widgetInstance = Widget_Model::getInstance();
  $widgetInfo = $widgetInstance->getWidgetInfo($widget);
  if (!$widgetInfo || !$widgetInfo->status) {
    return false;
  }
  $instance = $widgetInstance->getWidgetInstance($widget);
  return method_exists($instance, $func);
}

function widgetCallFunction($widget, $func)
{
  $widgetInstance = Widget_Model::getInstance();
  $widgetInfo = $widgetInstance->getWidgetInfo($widget);
  if ($widgetInfo && $widgetInfo->status) {
    $instance = $widgetInstance->getWidgetInstance($widget);
    if (method_exists($instance, $func)) {
      $args = func_get_args();
      array_shift($args);
      array_shift($args);
      return call_user_func_array(array($instance, $func), $args);
    }
  }
}

function plain($string)
{
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function t($string)
{
  $args = func_get_args();
  $string = Bl_Language::get(array_shift($args));
  $return = vsprintf($string, $args);
  return plain($return);
}

function c($value, $type = null, $showSymbol = true)
{
  static $n = null, $symbol = '';
  if (!isset($n)) {
    if (!isset($currencies)) {
      $siteInstance = Site_Model::getInstance();
      $currencies = $siteInstance->getCurrenciesList(true);
    }
    if (!isset($default)) {
      $default = Bl_Config::get('currency', false);
    }
    $n = 1;
    if ($default && isset($currencies[$default])) {
      $symbol = $currencies[$default]->symbol;
      if (isset($type) && isset($currencies[$type])) {
      } else if (isset($_SESSION['currency']) && isset($currencies[$_SESSION['currency']])) {
        $type = $_SESSION['currency'];
      } else {
        $type = false;
      }
      if ($type && $type != $default) {
        $symbol = $currencies[$type]->symbol;
        $n = $currencies[$default]->exchange / $currencies[$type]->exchange;
      }
    }
  }
  $value /= $n;
  if (hasFunction('currencyFormat')) {
    return callFunction('currencyFormat', $value, $symbol, $type, $showSymbol);
  } else {
    return ($showSymbol ? $symbol : '') . number_format($value, 2, '.', '');
  }
}

function url($path, $includeDomain = true)
{
  global $basePath, $domainUrl;
  ///browse/cheap-corsets-push-up-corsets.html
  //first check whether the path is in the subdomain lists.
  if(startsWith($path, '/')){
  	$path = substr($path, 1);
  }
  $subdomains = Bl_Config::get('subdomains', array());
  if(count($subdomains) > 0){
    $subdomain_supports = Bl_Config::get('subdomain-support');
    $position = strpos($path, '/');
    if($position){
    	$identical_url = substr($path, 0, $position);
    }else{
    	$identical_url = 'browse';
    }

    if(key_exists($identical_url, $subdomain_supports)){
    	//handling browse.
    	if($position){
    		$tempPath = substr($path, $position+1);
    	}else{
    		$tempPath = $path;
    	}
        if(preg_match('/.*-p\d+(-\d+)?.html/', $path)){
    	    $matchNum = preg_match('/\d+(-\d+)?.html/', $path, $matches);
    		if($matchNum > 0){
    			$sn = substr($matches[0], 0, count($matches[0]) - 6);
    			$productInstance = Product_Model::getInstance();
    			$productInfo = $productInstance->getProductInfoBySn($sn);
    			$categoryInstance = Taxonomy_Model::getInstance();
    			$termInfo = $categoryInstance->getTermInfo($productInfo->directory_tid1);
    			if(key_exists($termInfo->path_alias, $subdomains)){
    				return '//'.$subdomains[$termInfo->path_alias].'/'.$path;
    			}
    		}
    	}else if($identical_url == 'browse'){
    			//TODO NEED TO JUDGE WHETHER THE SUFFIX IS .HTML
	    		$categoryInstance = Taxonomy_Model::getInstance();
    			$suffix = '';
			  	if(endsWith($tempPath, '.html')){
			  		$suffix = '.html';
			  		$tempPath = substr($tempPath, 0, strlen($tempPath)-5);
			  	}else if(endsWith($tempPath, '/')){
			  		$suffix = '/';
			  		$tempPath = substr($tempPath, 0, strlen($tempPath)-1);
			  	}
	    		$termInfo = $categoryInstance->getTermInfoByPathAlias($tempPath);
	    		if($termInfo){
	    			if($termInfo->ptid1 == 0){
	    				if(key_exists($tempPath, $subdomains)){
	    					return '//'.$subdomains[$tempPath].'/';
	    				}
	    			}else{
	    				$topTerm = $categoryInstance->getTermInfo($termInfo->ptid1);
	    				if(key_exists($topTerm->path_alias, $subdomains)){
	    					return '//'.$subdomains[$topTerm->path_alias].'/'.$tempPath.$suffix;
	    				}
	    			}
	    		}
    	}
    }else{/*check whether it is the product url.*/}
  }
  
  $adminUrl = Bl_Config::get('admin.folder');
  if (isset($adminUrl) && $path !== '') {
    $paths = explode('/', $path);
    if ($paths[0] == 'admin') {
      $paths[0] = $adminUrl;
    }
    $path = implode('/', $paths);
  }
  if ($path && false === strpos(basename($path), '.') && substr($path, -1) != '/') {
    $path .= '/';
  }
  $returnUrl = transferUrl(($includeDomain ? $domainUrl : '') . $basePath . $path);
  if (startsWith($returnUrl, 'http://')) {
  	return substr($returnUrl, 5);
  }
  return $returnUrl;
}

function transferUrl($url) {
	$transfer_path_alias = Bl_Config::get('transfer_path_alias');
	foreach ($transfer_path_alias as $key => $value) {
		if (strpos($url, $key) !== false) {
			return str_replace($key, $transfer_path_alias[$key], $url);
		}
	}
	return $url;
}

function urlimg($presetName, $path)
{
	if(!isset($presetName)){
		return url('images/'.$path);
	}
  	return url('images/cache/' . $presetName . '/' . $path);
}

function urlBack($path)
{
  if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != '') {
    return $_SERVER['HTTP_REFERER'];
  } else {
    return url($path);
  }
}

function pagination($path, $count, $each, $page, $firstPath = null)
{
  $pages = ceil($count / $each);
  $page = intval($page);
  if ($pages && ($page < 1 || $page > $pages)) {
    goto404('Pagination not found.');
  }
  if ($pages <= 1) {
    return '';
  }
  $output = '';
  if ($pages > 10) {
    if ($page == 1) {
      $output .= '<span>' . t('First') . '</span>';
    } else {
      $output .= '<a href="' . url(isset($firstPath) ? $firstPath : strtr($path, array('%d' => 1))) . '">' . t('First') . '</a>';
    }
  }
  if ($pages > 1) {
    if ($page == 1) {
      $output .= '<span>' . t('Prev') . '</span>';
    } else {
      $output .= '<a href="' . url($page == 2 && isset($firstPath) ? $firstPath : strtr($path, array('%d' => $page - 1))) . '">' . t('Prev') . '</a>';
    }
  }
  $from = $page - 5;
  $end = $page + 5;
  if ($from < 1) {
    $end = min($end - $from + 1, $pages);
    $from = 1;
  }
  if ($end > $pages) {
    $from = max($from - $end + $pages, 1);
    $end = $pages;
  }
  for ($i = $from; $i <= $end; ++ $i) {
    if ($page == $i) {
      $output .= '<span class="current">' . $i . '</span>';
    } else {
      $output .= '<a href="' . url($i == 1 && isset($firstPath) ? $firstPath : strtr($path, array('%d' => $i))) . '">' . $i . '</a>';
    }
  }
  if ($pages > 1) {
    if ($page == $pages) {
      $output .= '<span>' . t('Next') . '</span>';
    } else {
      $output .= '<a href="' . url(strtr($path, array('%d' => $page + 1))) . '">' . t('Next') . '</a>';
    }
  }
  if ($pages > 10) {
    if ($page == $pages) {
      $output .= '<span>' . t('Last') . '</span>';
    } else {
      $output .= '<a href="' . url(strtr($path, array('%d' => $pages))) . '">' . t('Last') . '</a>';
    }
  }
  return $output;
}

function setMessage($message, $type = 'notice')
{
  if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = array();
  }
  $_SESSION['messages'][] = array(
    'type' => $type,
    'message' => $message,
  );
}

function getMessages($unset = true)
{
  $messages = isset($_SESSION['messages']) ? $_SESSION['messages'] : array();
  if ($unset) {
    unset($_SESSION['messages']);
  }
  return $messages;
}

function themeMessages()
{
  $output = "";
  $messages = getMessages();
  if (hasFunction('messages')) {
    return callFunction('messages', $messages);
  } else {
  	foreach ($messages as $message) {
  		if($message['type'] == 'error'){
  			$output = '<p class="pay-failure-word">';
  		}else{
  			$output = '<p class="success-word"><span>';
  		}
  		$output .=  $message['message']  . PHP_EOL;
  		$output .= '</span></p>';
  	}

//     $output = '<div class="messages">';
//     foreach ($messages as $message) {
//       $output .= '<div class="messages_box ' . $message['type'] . '"><p>' . $message['message'] . '</p></div>' . PHP_EOL;
//     }
//     $output .= '</div>';
    
    return $output;
  }
}

/**
 * 设置面包屑导航
 * Enter description here ...
 * @param unknown_type $set
 * @param unknown_type $reset
 * @throws Bl_403_Exception
 * @throws Bl_404_Exception
 */
function setBreadcrumb($set, $reset = false)
{
  static $breadcrumb = array();
  if ($reset) {
    $breadcrumb = array();
  }
  if (is_array($set)) {
    foreach ($set as $row) {
      $breadcrumb[] = $row;
    }
  }
  return $breadcrumb;
}

/**
 * 获得已有面包屑导航
 * Enter description here ...
 * @throws Bl_403_Exception
 * @throws Bl_404_Exception
 */
function getBreadcrumb()
{
  return setBreadcrumb(null);
}

/**
 * 
 * 生成面包屑导航代码
 */
function themeBreadcrumb()
{
  $breadcrumb = getBreadcrumb();
  if (hasFunction('breadcrumb')) {
    return callFunction('breadcrumb', $breadcrumb);
  } else {
	$output = "";
    $row = array_slice($breadcrumb, -2, 1);
    if(count($row) == 0){
    	$output .= '<a class="location" href="/" >';
    	$output .= '<span><</span>';
    	$output .= '<p>'.'Home'.'</p>';
    	$output .= '<div class="clear"></div>';
    	$output .= '</a>';
    }
    else{
    	$row = $row[0];
    	$title = isset($row['html']) && $row['html'] ? $row['title'] : plain($row['title']);
    	if($title == "Home"){
    		$output .= '<a class="location" href="'.url("product/topmenu").'" >';
    		$output .= '<span><</span>';
    		$output .= '<p>'.'All Categories'.'</p>';
    		$output .= '<div class="clear"></div>';
    		$output .= '</a>';
    	}else{
    		str_replace('&amp;amp;', '&amp;', $title);
    		if (isset($row['path'])) {
    			$output .= '<div class="classh2" style="padding:0 4.5%;">';
    			$output .= '<a href="' . url($row['path']) . '">';
    			$output .= '<p>'.$title.'</p>';
    			$output .= '</a>';
    			$output .= '</div>';
    		} else {
    			$output .= '<span>' . $title . '</span>';
    		}
    	}
    }
    return $output;
  }
}

function access($permission, $mode = 'and', $permissions = null)
{
  global $user;
  if ($user->uid == 1) {
    return true;
  }
  if (!isset($permissions)) {
    $permissions = $user->permissions;
  }
  if (is_array($permission)) {
    if ($mode == 'or') {
      foreach ($permission as $one) {
        if (in_array($one, $permissions)) {
          return true;
        }
      }
      return false;
    } else {
      foreach ($permission as $one) {
        if (!in_array($one, $permissions)) {
          return false;
        }
      }
      return true;
    }
  } else {
    return in_array($permission, $permissions);
  }
}

function strwidth($str, $width, $marker = '...')
{
  return mb_strimwidth($str, 0, $width, $marker, 'UTF8');
}

/**
 * Create CAPTCHA
 *
 * @access	public
 * @param	array	array of data for the CAPTCHA
 * @param	string	path to create the image in
 * @param	string	URL to the CAPTCHA image folder
 * @param	string	server path to font
 * @return	string
 */
if ( ! function_exists('create_captcha'))
{
	function create_captcha($data = '', $img_path = '', $img_url = '', $font_path = '')
	{
		$defaults = array('word' => '', 'img_path' => '', 'img_url' => '', 'img_width' => '120', 'img_height' => '30', 'font_path' => '', 'expiration' => 7200);

		foreach ($defaults as $key => $val)
		{
			if ( ! is_array($data))
			{
				if ( ! isset($$key) OR $$key == '')
				{
					$$key = $val;
				}
			}
			else
			{
				$$key = ( ! isset($data[$key])) ? $val : $data[$key];
			}
		}

		if ($img_path == '' OR $img_url == '')
		{
			return FALSE;
		}
		
		$img_path_parent_dir = substr($img_path ,0, strlen($img_path)-8);
		
		if (! @is_dir($img_path_parent_dir))
		{
			@mkdir($img_path_parent_dir);
		}

		if ( ! @is_dir($img_path))
		{
			@mkdir($img_path);
			//return FALSE;
		}

		if ( ! is_writable($img_path))
		{
			return FALSE;
		}

		if ( ! extension_loaded('gd'))
		{
			return FALSE;
		}

		// -----------------------------------
		// Remove old images
		// -----------------------------------

		list($usec, $sec) = explode(" ", microtime());
		$now = ((float)$usec + (float)$sec);

		$current_dir = @opendir($img_path);

		while($filename = @readdir($current_dir))
		{
			if ($filename != "." and $filename != ".." and $filename != "index.html")
			{
				$name = str_replace(".jpg", "", $filename);

				if (($name + $expiration) < $now)
				{
					@unlink($img_path.$filename);
				}
			}
		}

		@closedir($current_dir);

		// -----------------------------------
		// Do we have a "word" yet?
		// -----------------------------------

	   if ($word == '')
	   {
			$pool = '023456789abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

			$str = '';
			for ($i = 0; $i < 6; $i++)
			{
				$str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
			}

			$word = $str;
	   }

		// -----------------------------------
		// Determine angle and position
		// -----------------------------------

		$length	= strlen($word);
		$angle	= ($length >= 6) ? rand(-($length-6), ($length-6)) : 0;
		$x_axis	= rand(6, (360/$length)-16);
		$y_axis = ($angle >= 0 ) ? rand($img_height, $img_width) : rand(6, $img_height);

		// -----------------------------------
		// Create image
		// -----------------------------------

		// PHP.net recommends imagecreatetruecolor(), but it isn't always available
		if (function_exists('imagecreatetruecolor'))
		{
			$im = imagecreatetruecolor($img_width, $img_height);
		}
		else
		{
			$im = imagecreate($img_width, $img_height);
		}

		// -----------------------------------
		//  Assign colors
		// -----------------------------------

		$bg_color		= imagecolorallocate ($im, 255, 255, 255);
		$border_color	= imagecolorallocate ($im, 153, 102, 102);
		$text_color		= imagecolorallocate ($im, 204, 153, 153);
		$grid_color		= imagecolorallocate($im, 255, 182, 182);
		$shadow_color	= imagecolorallocate($im, 255, 240, 240);

		// -----------------------------------
		//  Create the rectangle
		// -----------------------------------

		ImageFilledRectangle($im, 0, 0, $img_width, $img_height, $bg_color);

		// -----------------------------------
		//  Create the spiral pattern
		// -----------------------------------

		$theta		= 1;
		$thetac		= 7;
		$radius		= 16;
		$circles	= 20;
		$points		= 32;

		for ($i = 0; $i < ($circles * $points) - 1; $i++)
		{
			$theta = $theta + $thetac;
			$rad = $radius * ($i / $points );
			$x = ($rad * cos($theta)) + $x_axis;
			$y = ($rad * sin($theta)) + $y_axis;
			$theta = $theta + $thetac;
			$rad1 = $radius * (($i + 1) / $points);
			$x1 = ($rad1 * cos($theta)) + $x_axis;
			$y1 = ($rad1 * sin($theta )) + $y_axis;
			imageline($im, $x, $y, $x1, $y1, $grid_color);
			$theta = $theta - $thetac;
		}

		// -----------------------------------
		//  Write the text
		// -----------------------------------

		$use_font = ($font_path != '' AND file_exists($font_path) AND function_exists('imagettftext')) ? TRUE : FALSE;

		if ($use_font == FALSE)
		{
			$font_size = 5;
			$x = rand(0, $img_width/($length/3));
			$y = 0;
		}
		else
		{
			$font_size	= 16;
			$x = rand(0, $img_width/($length/1.5));
			$y = $font_size+2;
		}

		for ($i = 0; $i < strlen($word); $i++)
		{
			if ($use_font == FALSE)
			{
				$y = rand(0 , $img_height/2);
				imagestring($im, $font_size, $x, $y, substr($word, $i, 1), $text_color);
				$x += ($font_size*2);
			}
			else
			{
				$y = rand($img_height/2, $img_height-3);
				imagettftext($im, $font_size, $angle, $x, $y, $text_color, $font_path, substr($word, $i, 1));
				$x += $font_size;
			}
		}


		// -----------------------------------
		//  Create the border
		// -----------------------------------

		imagerectangle($im, 0, 0, $img_width-1, $img_height-1, $border_color);

		// -----------------------------------
		//  Generate the image
		// -----------------------------------

		$img_name = $now.'.jpg';

		ImageJPEG($im, $img_path.$img_name);

		$img = "<img src=\"$img_url$img_name\" width=\"$img_width\" height=\"$img_height\" style=\"border:0;\" alt=\" \" />";

		ImageDestroy($im);

		return array('word' => $word, 'time' => $now, 'image' => $img);
	}
}

function getCaptcha($sess_name = null, $data = '')
{
	$template = Bl_Config::get('template');
	$captcha = create_captcha($data, BLROOT . '/httpdocs/images/'.$template.'/captcha/', url('images/'.$template.'/captcha'), null);
	$sess_name = $sess_name ? $sess_name : 'captcha_name';
	$_SESSION[$sess_name] = $captcha['word'];
	return $captcha['image'];
}

/**Added by pzzhang**/
/**
 * Get current page url
 */
function curPageURL() {
 $pageURL = 'http';
 if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}


function subDomainSupport($oldRelativePath, $domainName){
	$subdomains = Bl_Config::get('subdomains');
	$subdomain_supports = Bl_Config::get('subdomain-support');
	if(startsWith($oldRelativePath, "/")){
		$oldRelativePath = substr($oldRelativePath, '/');
	}
	$key = array_search($domainName, $subdomains);
	if($key != false){
		if('' == $oldRelativePath){
			return 'browse/'.$key.'.html';
		}else if(preg_match('/(.*)(-\w)(.html|\/)/', $oldRelativePath, $matches)){
			$identity = array_search($matches[2], $subdomain_support);
			if($identity){
				return $identity .'/'.$key.'-'.$matches[1].$matches[3];
			}
		}
	}
	return $oldRelativePath;
}
function categoryURL($oldUrl){
  $newUrl = '';
  $categoryRouter = Bl_Config::get('categoryRouter', array());
  foreach($categoryRouter as $k=>$v){
    if(startsWith($oldUrl, $k)){
      $newUrl = str_replace($k, $v, $oldUrl);
      break;
    }
  }
  if($newUrl == ''){
    $newUrl = $oldUrl;
  }
  $newUrl .= '.html';
  return $newUrl;
}

function associateURL($type, $pageNum, $pageNumCount){
  $curPageUrl = curPageURL();
  $url_segments = split('/', $curPageUrl);
  $shift = -1;
  foreach($url_segments as $k=>$v){
    if($shift > 0){
      $shift--;
    }
    if ($v == 'pitems'){
      $shift = 2;
    }
    if($shift == 0){
      #this is current search page number.
      if($type == 'PREV' && $pageNum > 1){
        $url_segments[$k] = ''.($pageNum - 1);
      }else if($type == 'NEXT' && $pageNum < $pageNumCount){
        $url_segments[$k] = ''.($pageNum + 1);
      }else if($type == 'FIRST'){
        $url_segments[$k] = '1';
      }else if($type == 'LAST'){
        $url_segments[$k] = ''.$pageNumCount;
      }
      break;
    }
  }
  return implode('/', $url_segments);
}

function themeResourceURI($relativePath){
  $themeName = Bl_Config::get('template', 'default');
  return url('templates/' . $themeName . '/'.$relativePath);
}

function ceil_dec($number,$precision,$separator = ".")
{
    $numberpart=explode($separator,$number);  
    $numberpart[1]=substr_replace($numberpart[1],$separator,$precision,0);
    if($numberpart[0]>=0)
    {$numberpart[1]=ceil($numberpart[1]);}
    else
    {$numberpart[1]=floor($numberpart[1]);}

    $ceil_number= array($numberpart[0],$numberpart[1]);
    return implode($separator,$ceil_number);
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    $start  = $length * -1; //negative
    return (substr($haystack, $start) === $needle);
}
/**
 * Function array_insert().
 *
 * Returns the new number of the elements in the array.
 *
 * @param array $array Array (by reference)
 * @param mixed $value New element
 * @param int $offset Position
 * @return int
 */
function array_insert(&$array, $value, $offset)
{
    if (is_array($array)) {
        $array  = array_values($array);
        $offset = intval($offset);
        if ($offset < 0 || $offset >= count($array)) {
            array_push($array, $value);
        } elseif ($offset == 0) {
            array_unshift($array, $value);
        } else {
            $temp  = array_slice($array, 0, $offset);
            array_push($temp, $value);
            $array = array_slice($array, $offset);
            $array = array_merge($temp, $array);
        }
    } else {
        $array = array($value);
    }
    return count($array);
}

function array_batch_insert(&$array, $value, $offset, $repeatTime){
  
    $tempArray = array();
    for($i=0; $i< $repeatTime;$i++){
      $tempArray[]=clone $value;
    }
    if (is_array($array)) {
        $array  = array_values($array);
        $offset = intval($offset);
        if ($offset < 0 || $offset >= count($array)) {
            //array_push($array, $tempArray);
            array_splice($array, count($array), 0, $tempArray);
        } else{
            array_splice($array, $offset, 0, $tempArray);
        }
    } else {
      $array = $tempArray;
    }
    return count($array);
}

function ip_in_range($ip, $range_start, $range_end){
	$ip_segs = explode('.', $ip);
	if (count($ip_segs) <= 1) {
		return false;
	}
	$start_ip_segs = explode('.', $range_start);
	$end_ip_segs = explode('.', $range_end);
	
	if(intval($ip_segs[0]) < intval($start_ip_segs[0]) 
		|| intval($ip_segs[1]) < intval($start_ip_segs[1]) 
		|| intval($ip_segs[2]) < intval($start_ip_segs[2]) 
		|| intval($ip_segs[3]) < intval($start_ip_segs[3])){
			return false;
	}
	if(intval($ip_segs[0]) > intval($end_ip_segs[0]) 
		|| intval($ip_segs[1]) > intval($end_ip_segs[1]) 
		|| intval($ip_segs[2]) > intval($end_ip_segs[2]) 
		|| intval($ip_segs[3]) > intval($end_ip_segs[3])){
			return false;
	}
	return true;
}

function isSearchEngine() {
	$ipAdd = ipAddress();
	return ip_in_range($ipAdd, '64.233.160.0', '64.233.191.255')
	||ip_in_range($ipAdd, '66.102.0.0', '66.102.15.255')
	||ip_in_range($ipAdd, '66.249.64.0', '66.249.95.255')
	||ip_in_range($ipAdd, '72.14.192.0', '72.14.255.255')
	||ip_in_range($ipAdd, '74.125.0.0', '74.125.255.255')
	||ip_in_range($ipAdd, '209.85.128.0', '209.85.255.255')
	||ip_in_range($ipAdd, '216.239.32.0', '216.239.63.255')
	||ip_in_range($ipAdd, '66.175.219.250', '66.175.219.250')
	||ip_in_range($ipAdd, '50.23.131.206', '50.23.131.206');
}

function normalizeFabric($fabric) {
	if (empty($fabric)) {
		return '';
	}
	return strtolower(implode('-', explode(' ', $fabric)));
}

function echoOrderItemProperties($orderItemData) {
	foreach($orderItemData as $dk => $dv):
	if (strlen(trim($dv)) > 0) {
		if ($dk == "Color") {
			if (preg_match('/^As Picture:.*\.jpg$/', $dv)) {
				echo '<table><tr><td style="width:50px;padding:0px;border:none"><strong style="display:inline-block;font-size:12px;">'.$dk. '&nbsp;:&nbsp;</strong></td><td style="border:none;padding:0px"><img style="display:inline;border:solid 1px #ccc; width:50px;" src="' . urlimg('118x178', substr($dv, 11)) . '" /></td></tr></table>';
				continue;
			}
		}
		echo '<p class="prAttr"><strong>'.$dk.'&nbsp;:&nbsp;</strong>'.$dv.'</p>';
	}
	endforeach;
}


function size($size){
	$size = str_replace('.25', ' ¼', $size);
	$size = str_replace('.5', ' ½', $size);
	$size = str_replace('.75', ' ¾', $size);
	return $size;
}

function picurlconvert($filepath){
	$backdata = "";
	$backdata = str_replace("/", "-", $filepath);
	return $backdata;
}

