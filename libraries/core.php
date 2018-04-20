<?php
final class Bl_Core
{
	private static $_uri;
	private static $_paths;
	private static $_router = array(
    'folder'     => null,
    'controller' => 'default',
    'action'     => 'index',
    'arguments'  => array(),
	);
	private static $_instances = array();

	private static function getLoadFilename($name)
	{
		return trim(strtolower(strtr($name, '_', '/')), '/') . '.php';
	}

	public static function loadLibrary($library)
	{
		$filename = self::getLoadFilename($library);
		if (is_file(LIBPATH . '/' . $filename)) {
			require_once LIBPATH . '/' . $filename;
		}
	}

	public static function loadModel($model)
	{
		if (strcasecmp(substr($model, -6), '_Model') == 0) {
			$model = substr($model, 0, -6);
		}
		$filename = self::getLoadFilename($model);
		if (is_file(SITESPATH . '/default/models/' . $filename)) {
			require_once SITESPATH . '/default/models/' . $filename;
		}
	}

	public static function init()
	{
		global $db, $user, $basePath;

		Bl_Core::loadLibrary('common');
		timer();
		ob_start();
		spl_autoload_register('Bl_Core::loadModel');
		Bl_Config::load();
		Bl_Core::loadLibrary(Bl_Config::get('log.type', 'log.db'));
		Bl_Core::loadLibrary(Bl_Config::get('cache.type', 'cache.file'));

		Bl_Core::loadLibrary('database');
		$db = new Bl_Database();
		$db->connect(Bl_Config::get('db'));
		Bl_Config::loadSettings();

		if (is_file(SITESPATH . '/default/hook.php')) {
			require_once SITESPATH . '/default/hook.php';
		}
		if (is_file(SITEPATH . '/hook.php')) {
			require_once SITEPATH . '/hook.php';
		}
		$template = Bl_Config::get('template', HOSTNAME);
		if ($template && is_file(TPLPATH . '/' . $template . '/' . $template . '.php')) {
			require_once TPLPATH . '/' . $template . '/' . $template . '.php';
		}

		date_default_timezone_set(Bl_Config::get('timezone', 'Asia/Shanghai'));
		Bl_Language::load(Bl_Config::get('lang', 'zh-cn'));

		if (!preg_match('/^(?:[\w-:]+\.?)+$/', HOSTNAME)) {
			header('HTTP/1.1 400 Bad Request');
			throw new Bl_General_Exception('Hostname error.');
		}

		if ($path = trim(dirname($_SERVER['SCRIPT_NAME']), '\\/')) {
			$basePath = '/' . $path . '/';
		} else {
			$basePath = '/';
		}
		unset($path);

		if (isset($_SERVER['PATH_INFO'])) {
			$uri = ltrim($_SERVER['PATH_INFO'], '/');
		} else if(isset($_SERVER['ORIG_PATH_INFO'])){
			$uri = ltrim($_SERVER['ORIG_PATH_INFO'], '/');
		}else{
			$uri = '';
		}

		if ($uri && false === strpos(basename($uri), '.') && substr($uri, -1) != '/') {
			header('Location: ' . $basePath . $uri . '/', true, 301);
			exit;
		}

		//only use one slash for releated slashes.
		$uri = trim(preg_replace('/\/{2,}/', '/', $uri), '/');

		//for second level domain support
		$uri = subDomainSupport($uri, $_SERVER['HTTP_HOST']);

		//added by pzzhang for briefing urls.
		self::dynamicRouter($uri);
		self::staticRouter($uri);

		//handle optimized product and category url.
		if(substr_count($uri, '/') == 0 && endsWith($uri, '.html')){
			//split by -p, then get the last one.
			$isProductUrl = false;
			$splits = explode('-p', $uri);
			if(count($splits) > 1){
				$lastSeg = $splits[count($splits)-1];
				if(preg_match('/^\d+(-\d+)?.html/', $lastSeg, $matches) > 0){
					//no branding.
					$isProductUrl = true;
					$sn = substr($matches[0], 0, strlen($matches[0]) - 5);
				}
				else if(preg_match('/^[A-Z]{2}_.*.html/', $lastSeg, $matches) > 0){
					//branding.
					$isProductUrl = true;
					$sn = substr($matches[0], 0, strlen($matches[0]) - 5);
				}
			}
			 
			if($isProductUrl){
				//this is a product.
				$productInstance = Product_Model::getInstance();
				$productInfo = $productInstance->getProductInfoBySn($sn);
				//$uri = 'product/term/'.$productInfo->directory_tid.'/' .$productInfo->path_alias.'.html';
				$uri = 'product/view/'.$productInfo->pid.'/';
			}else{
				//this is a category.
				$uri = 'product/browse/'.$uri;
			}
		}

		self::$_uri = $uri;

		$paths = $uri == '' ? array() : explode('/', $uri);
		$adminUrl = Bl_Config::get('admin.folder');
		if (isset($adminUrl) && isset($paths[0])) {
			if ($adminUrl != 'admin' && $paths[0] == 'admin') {
				goto404('Administrator page has an alias.');
			}
			if ($paths[0] == $adminUrl) {
				$paths[0] = 'admin';
			}
		}
		define('IN_ADMIN', isset($paths[0]) && $paths[0] == 'admin');
		if (!IN_ADMIN) {
			$blackList = Bl_Config::get('black_list', array());
			if (!empty($blackList)) {
				$ip = ipAddress();
				foreach ($blackList as $cidr) {
					if (ipCheck($ip, $cidr)) {
						goto404('IP banned.');
					}
				}
			}
		}
		self::$_paths = $paths;
		session_name(Bl_Config::get('session.name', 'sid'));
		//session_set_cookie_params(0, '/', '.'.SITENAME);
		Bl_Core::loadLibrary(Bl_Config::get('session.type', 'session.db'));
		session::init();
		session_start();
	}

	public static function run()
	{
		$paths = self::$_paths;
		$controllerPath = SITESPATH . '/default/controllers';
		if (isset($paths[0]) && is_dir($controllerPath . '/' . strtolower($paths[0]))) {
			self::$_router['folder'] = strtolower($paths[0]);
			array_shift($paths);
			$controllerPath .= '/' . self::$_router['folder'];
		}
		if (isset($paths[0])) {
			self::$_router['controller'] = strtolower($paths[0]);
			array_shift($paths);
		}
		$controllerFile = $controllerPath . '/' . self::$_router['controller'] . '.php';
		if (!is_file($controllerFile)) {
			goto404('Controller file <em>' . $controllerFile . '</em> not found.');
		}
		require_once $controllerFile;
		$controllerClass = (isset(self::$_router['folder']) ? (ucfirst(self::$_router['folder']) . '_') : '') .  ucfirst(self::$_router['controller']) . '_Controller';
		if (!class_exists($controllerClass)) {
			goto404('Controller class <em>' . $controllerClass . '</em> not found.');
		}
		if (method_exists($controllerClass, '__router') && $routers = call_user_func(array($controllerClass, '__router'), $paths)) {
			foreach ($routers as $key => $value) {
				self::$_router[$key] = $value;
			}
			unset($routers);
		} else {
			if (isset($paths[0])) {
				$action = $paths[0];
				self::$_router['action'] = $action;
				array_shift($paths);
				if ($action[0] == '_') {
					goto404('Action <em>' . $action . 'Action</em> is invalid.');
				}
			}
			self::$_router['arguments'] = $paths;
		}
		$hookFunction = (isset(self::$_router['folder']) ? (self::$_router['folder'] . '_') : '') . self::$_router['controller'] . '_' . self::$_router['action'];
		$hookPageFunction = (isset(self::$_router['folder']) ? (self::$_router['folder'] . '_') : '') . 'page';
		if (hasFunction($hookFunction)) {
			self::$_instances[$controllerClass] = new $controllerClass();
			if (hasFunction($hookPageFunction)) {
				callFunction($hookPageFunction, self::$_instances[$controllerClass]);
			}
			$paths = self::$_router['arguments'];
			array_unshift($paths, self::$_instances[$controllerClass]);
			call_user_func_array('hook_' . $hookFunction, $paths);
		} else {
			$actionMethod = self::$_router['action'] . 'Action';
			if (!method_exists($controllerClass, $actionMethod)) {
				goto404('Action <em>' . $controllerClass . '::' . $actionMethod . '</em> not found.');
			}
			self::$_instances[$controllerClass] = new $controllerClass();
			if (hasFunction($hookPageFunction)) {
				callFunction($hookPageFunction, self::$_instances[$controllerClass]);
			}
			self::dispatch();
		}
		if (Bl_Config::get('compress', false) && !IN_ADMIN) {
			echo preg_replace('/\s+/', ' ', ob_get_clean());
		} else {
			echo ob_get_clean();
		}
	}

	private static function staticRouter(&$uri)
	{
		global $db;
		$cacheId = 'static-routers';
		if ($cache = cache::get($cacheId)) {
			$staticRouters = $cache->data;
		} else {
			$result = $db->query('SELECT src, dest FROM path_alias');
			$staticRouters = array_merge(Bl_Config::get('routers', array()), $result->columnWithKey('dest'));
			
			cache::save($cacheId, $staticRouters);
		}
		if ($staticRouters) {
			$path = $uri;
			$pos = strlen($path);
			do {
				$key = strtolower($path);
				if (array_key_exists($key, $staticRouters)) {
					$uri = trim(preg_replace('/\/{2,}/', '/', strval($staticRouters[$key])), '/') . substr($uri, $pos);
					return;
				}
				$pos = strrpos($path, '/');
				if ($pos) {
					$path = substr($path, 0, $pos);
				}
			} while ($pos);
		}
	}


	public static function dynamicRouter(&$uri){
		$dynamicRouters = Bl_Config::get('dynamicRouters', array());

		if ($dynamicRouters) {
			$path = $uri;
			foreach ($dynamicRouters as $k=> $v){
				$pos = strpos($path, $k);
				if($pos === 0){
					$uri = substr($path, 0, $pos) . $v . substr($path, $pos + strlen($k));
					break;
				}
			}
		}
	}

	private static function dispatch($router = null, $log = true)
	{
		if (!isset($router)) {
			$router = self::$_router;
		}

		$controllerClass = (isset($router['folder']) ? (ucfirst($router['folder']) . '_') : '') . ucfirst($router['controller']) . '_Controller';
		if (!class_exists($controllerClass, false)) {
			$controllerPath = SITESPATH . '/default/controllers';
			if (isset($router['folder'])) {
				$controllerPath .= '/' . $router['folder'] ;
			}
			$controllerFile = $controllerPath . '/' . $router['controller'] . '.php';
			if (!is_file($controllerFile)) {
				die('Controller file ' . $controllerFile . ' not found.');
			}
			require_once $controllerFile;
			if (!class_exists($controllerClass)) {
				die('Controller class ' . $controllerClass . ' not found.');
			}
		}
		if (!isset(self::$_instances[$controllerClass])) {
			self::$_instances[$controllerClass] = new $controllerClass();
		}
		$controllerInstance = self::$_instances[$controllerClass];
		$actionMethod = $router['action'] . 'Action';

		if ($log && Bl_Config::get('log.access', false) && (!isset($router['folder']) || !IN_ADMIN)) {
			log::save('200', 'Access page.', $_SERVER['HTTP_USER_AGENT']);
		}
		call_user_func_array(array($controllerInstance, $actionMethod), $router['arguments']);
	}

	public static function errorDispatch(Exception $ex)
	{
		ob_clean();
		try {
			Bl_Core::dispatch(array(
        'controller' => 'error',
        'action' => 'error',
        'arguments' => array($ex),
			), false);
		} catch (Exception $ignoreEx) {
			die($ex->getMessage());
		}
	}

	public static function getUri()
	{
		return self::$_uri;
	}

	public static function getRouter()
	{
		return self::$_router;
	}

	public static function arg($index, $default = null)
	{
		static $paths = null;
		if (!isset($paths)) {
			$paths = explode('/', self::$_uri);
		}
		return isset($paths[$index]) ? $paths[$index] : $default;
	}


	/**added by pengzzhang for api service**/
	public static function initApiService(){
		global $db, $user, $basePath;

		Bl_Core::loadLibrary('common');
		timer();
		ob_start();
		spl_autoload_register('Bl_Core::loadModel');
		Bl_Config::load();
		Bl_Core::loadLibrary(Bl_Config::get('log.type', 'log.db'));
		Bl_Core::loadLibrary(Bl_Config::get('cache.type', 'cache.file'));

		Bl_Core::loadLibrary('database');
		$db = new Bl_Database();
		$db->connect(Bl_Config::get('db'));
		Bl_Config::loadSettings();

		if ($path = trim(dirname($_SERVER['SCRIPT_NAME']), '\\/')) {
			$basePath = '/' . $path . '/';
		} else {
			$basePath = '/';
		}
		unset($path);
		//load php rpc server
		require_once LIBPATH . '/PHPRPC/phprpc_server.php';
		
	}
	 
	public static function runApiService(){
		self::$_router['folder'] = 'api';
		self::$_router['controller'] = 'server';
		self::$_router['action'] = 'service';
		$controllerPath = SITESPATH . '/default/controllers';
		$controllerPath .= '/' . self::$_router['folder'];
		$controllerFile = $controllerPath . '/' . self::$_router['controller'] . '.php';
		require_once $controllerFile;
		$controllerClass = (isset(self::$_router['folder']) ? (ucfirst(self::$_router['folder']) . '_') : '') .  ucfirst(self::$_router['controller']) . '_Controller';

		$actionMethod = self::$_router['action'] . 'Action';
		self::$_instances[$controllerClass] = new $controllerClass();
		self::dispatch();
	}
	/**end added by pengzzhang for api service**/
}

abstract class Bl_Controller
{
	/**
	 * @var Bl_View
	 */
	public $view = null;

	public function __construct()
	{
		$this->view = new Bl_View();
		$this->init();
	}

	public function init()
	{
	}

	public function isPost()
	{
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}
}

abstract class Bl_Model
{
	protected static function getInstance($className)
	{
		static $_instances = array();
		if (!isset($_instances[$className])) {
			$_instances[$className] = new $className;
		}
		return $_instances[$className];
	}

	public function callFunction($func)
	{
		$args = func_get_args();
		array_shift($args);
		$className = get_class($this);
		if (strcasecmp(substr($className, -6), '_Model') == 0) {
			$hookFunc = strtolower(substr($className, 0, -6)) . '_' . $func;
		}
		if (function_exists('hook_' . $hookFunc)) {
			array_unshift($args, $this);
			return call_user_func_array('hook_' . $hookFunc, $args);
		} else if (method_exists($this, $func)) {
			return call_user_func_array(array($this, $func), $args);
		}
	}
}

final class Bl_View
{
	private $_data = array();

	public function render($templateFile, $variables = null)
	{
		global $basePath;
		static $_template;
		if (!isset($_template)) {
			if (isset($_SESSION['preview'])) {
				$_template = $_SESSION['preview'];
			} else {
				$_template = Bl_Config::get('template', HOSTNAME);
			}
		}
		$this->assign('tpldir', $_template ? ($basePath . 'templates/' . $_template) : '');
		$this->assign('scripts', $this->renderJs());
		$this->assign('styles', $this->renderCss());
		$this->assign('siteInfo', Bl_Config::get('siteInfo'));

		if (isset($variables) && is_array($variables)) {
			$this->assign($variables);
		}
		extract($this->_data, EXTR_OVERWRITE);

		if (is_file(SITEPATH . '/views/' . $templateFile)) {
			include SITEPATH . '/views/' . $templateFile;
		} else if ($_template && is_file(TPLPATH . '/' . $_template . '/views/' . $templateFile)) {
			include TPLPATH . '/' . $_template . '/views/' . $templateFile;
		} else if (is_file(SITESPATH . '/default/views/' . $templateFile)) {
			include SITESPATH . '/default/views/' . $templateFile;
		} else {
			throw new Bl_General_Exception('View file <em>' . $templateFile . '</em> not found.');
		}
	}

	public function assign($key, $value = null)
	{
		if (is_array($key)) {
			foreach ($key as $k => $value) {
				$this->_data[$k] = $value;
			}
		} else {
			$this->_data[$key] = $value;
		}
		return $this;
	}

	public function setTitle($title, $keywords = '', $description = '', $var1 = '', $var2 = '', $var3 = '', $var4 = '', $var5 = '', $var6 = '')
	{
		$this->assign('docTitle', $title);
		$this->assign('docKeywords', $keywords);
		$this->assign('docDescription', $description);
		$this->assign('docvar1', $var1);
		$this->assign('docvar2', $var2);
		$this->assign('docvar3', $var3);
		$this->assign('docvar4', $var4);
		$this->assign('docvar5', $var5);
		$this->assign('docvar6', $var6);
		return $this;
	}

	public function addJs($path)
	{
		if (!isset($this->_data['scripts'])) {
			$this->_data['scripts'] = array();
		}
		$this->_data['scripts'][] = $path;
		return $this;
	}

	public function renderJs()
	{
		static $scripts;
		if (!isset($scripts)) {
			$scripts = '';
			if (isset($this->_data['scripts'])) {
				foreach ($this->_data['scripts'] as $path) {
					$scripts .= '<script type="text/javascript" src="' . $path . '"></script>' . PHP_EOL;
				}
			}
		}
		return $scripts;
	}

	public function addCss($path)
	{
		if (!isset($this->_data['styles'])) {
			$this->_data['styles'] = array();
		}
		$this->_data['styles'][] = $path;
		return $this;
	}

	public function renderCss()
	{
		static $styles;
		if (!isset($styles)) {
			$styles = '';
			if (isset($this->_data['styles'])) {
				foreach ($this->_data['styles'] as $path) {
					$styles .= '<link rel="stylesheet" href="' . $path . '" type="text/css">' . PHP_EOL;
				}
			}
		}
		return $styles;
	}
	public function themeResourceURI($relativePath){
		$themeName = Bl_Config::get('template', 'default');
		return url('templates/' . $themeName . '/'.$relativePath);
	}

	public function themeFileExists($relativePath){
		$themeName = Bl_Config::get('template', 'default');
		return file_exists(DOCROOT.'/templates/' . $themeName . '/'.$relativePath);
	}
}

final class Bl_Config
{
	private static $_config;
	private static $_set;

	public static function load()
	{
		global $domainUrl;
		if (!isset(self::$_config)) {
			if (is_file(SITESPATH . '/default/config.php') && (require_once SITESPATH . '/default/config.php') && isset($config)) {
				self::$_config = $config;
				unset($config);
			} else {
				self::$_config = array();
			}
			$hostname = strtolower($_SERVER['HTTP_HOST']);
			if(key_exists('subdomains', self::$_config) && in_array($hostname, self::$_config['subdomains'])){
				$hostname = 'bogolingerie.com';
			}
			if (false !== ($pos = strpos($hostname, ':'))) {
				$hostname = substr($hostname, 0, $pos);
			}
			if (substr($hostname, 0, 4) == 'www.') {
				$hostname = substr($hostname, 4);
			}
			if (isset(self::$_config['sites']) && isset(self::$_config['sites'][$hostname])) {
				$hostname = self::$_config['sites'][$hostname];
			}
			define('HOSTNAME', $hostname);
			define('SITEPATH', SITESPATH . '/' . $hostname);
			if (!is_dir(SITEPATH)) {
				goto404('Sitepath <em>' . SITEPATH . '</em> Not Found.');
			}
			if (is_file(SITEPATH . '/config.php') && (require_once SITEPATH . '/config.php') && isset($config)) {
				if (isset(self::$_config['routers']) && isset($config['routers'])) {
					self::$_config['routers'] = array_merge(self::$_config['routers'], $config['routers']);
					unset($config['routers']);
				}
				self::$_config = array_merge(self::$_config, $config);
				unset($config);
			}
			$hostname = strtolower($_SERVER['HTTP_HOST']);
			$subdomains = Bl_Config::get('subdomains');
			if($subdomains && in_array($hostname, $subdomains)){
				$hostname = 'www.bogolingerie.com';
			}
			/*      if (self::get('www', true)) {
			 $domainUrl = 'http://' . (substr($hostname, 0, 4) == 'www.' ? $hostname : ('www.' . $hostname));
			 } else {*/
			$domainUrl = 'http://' . $hostname;
			//}
		}
		return self::$_config;
	}

	public static function loadSettings()
	{
		global $db;
		static $_settings;
		if (!isset($_settings)) {
			$cacheId = 'settings';
			if ($cache = cache::get($cacheId)) {
				$_settings = $cache->data;
			} else {
				$result = $db->query('SELECT `key`, `value` FROM settings');
				$_settings = $result->columnWithKey('key', 'value');
				cache::save($cacheId, $_settings);
			}
			foreach ($_settings as $key => $value) {
				self::$_config[$key] = unserialize($value);
			}
		}
	}

	public static function save()
	{
		global $db;
		foreach (self::$_set as $key => $value)
		{
			$set = array('value' => serialize($value));
			$db->update('settings', $set, array('key' => $key));
			if (!$db->affected()) {
				$set['key'] = $key;
				$db->insert('settings', $set, true);
			}
		}
		cache::remove('settings');
	}

	public static function get($key = null, $default = null)
	{
		if (isset($key)) {
			return key_exists($key, self::$_config) ? self::$_config[$key] : $default;
		} else {
			return self::$_config;
		}
	}

	public static function set($key, $value)
	{
		self::$_config[$key] = $value;
		self::$_set[$key] = $value;
	}
}

final class Bl_Language
{
	private static $_lang;

	public static function load($filename)
	{
		if (!isset(self::$_lang)) {
			self::$_lang = array();
			if (is_file(SITEPATH . '/languages/' . $filename . '.php') && (require_once SITEPATH . '/languages/' . $filename . '.php') && isset($lang)) {
				self::$_lang += $lang;
				unset($lang);
			}
			$template = Bl_Config::get('template', HOSTNAME);
			if ($template && is_file(TPLPATH . '/' . $template . '/languages/' . $filename . '.php') && (require_once TPLPATH . '/' . $template . '/languages/' . $filename . '.php') && isset($lang)) {
				self::$_lang += $lang;
				unset($lang);
			}
			if (is_file(SITESPATH . '/default/languages/' . $filename . '.php') && (require_once SITESPATH . '/default/languages/' . $filename . '.php') && isset($lang)) {
				self::$_lang += $lang;
				unset($lang);
			}
		}
	}

	public static function get($string)
	{
		return isset(self::$_lang[$string]) ? self::$_lang[$string] : $string;
	}
}

final class Bl_Plugin
{
	private static $_list;

	public static function getList($name, $reset = false)
	{
		if (!isset(self::$_list[$name]) || $reset) {
			$cacheId = 'plugin-' . $name;
			if ($cache = cache::get($cacheId)) {
				self::$_list[$name] = $cache->data;
			} else {
				self::$_list[$name] = array();
				$pluginPath = SITESPATH . '/default/plugins/' . $name;
				if ($dh = opendir($pluginPath)) {
					while(false !== ($file = readdir($dh))) {
						if ($file[0] == '.') {
							continue;
						}
						if (is_dir($pluginPath . '/' . $file) && is_file($pluginPath . '/' . $file . '/' . $file . '.info')) {
							$info = parse_ini_file($pluginPath . '/' . $file . '/' . $file . '.info');
							if (!empty($info) && isset($info['name'])) {
								$info['id'] = $file;
								self::$_list[$name][$file] = (object)$info;
							}
						}
					}
					closedir($dh);
				}
				cache::save($cacheId, self::$_list[$name]);
			}
		}
		return self::$_list[$name];
	}

	public static function getInstance($name, $plugin)
	{
		static $_instances = array();
		if (is_object($plugin)) {
			$plugin = $plugin->id;
		}
		if (isset($_instances[$name]) && isset($_instances[$name][$plugin])) {
			return $_instances[$name][$plugin];
		}
		$list = self::getList($name);
		if (!isset($list[$plugin])) {
			return false;
		}
		$id = $list[$plugin]->id;
		$pluginPath = SITESPATH . '/default/plugins/' . $name;
		if (is_file($pluginPath . '/' . $name . '.php')) {
			require_once $pluginPath . '/' . $name . '.php';
		}
		if (!isset($_instances[$name])) {
			$_instances[$name] = array();
		}
		$filename = $pluginPath . '/' . $id . '/' . $id . '.php';
		if (is_file($filename)) {
			require_once $filename;
			$className = ucfirst($id);
			$_instances[$name][$id] = new $className;
		} else {
			$_instances[$name][$id] = false;
		}
		return $_instances[$name][$id];
	}
}
