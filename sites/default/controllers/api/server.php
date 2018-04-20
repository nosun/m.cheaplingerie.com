<?php 
class Api_Server_Controller{
	private $_open_models = array('Api_Product_Controller', 'Api_User_Controller', 'Api_Order_Controller');
	private $_rpcServer;
	
	public function serviceAction(){
		$this->_rpcServer = new PHPRPC_Server(); 
		$this->registerPublicAPIs();
		$this->_rpcServer->start();
	}
	
	private function registerPublicAPIs(){
		foreach($this->_open_models as $cls){
			Api_Server_Controller::loadAPIFile($cls);
			//TODO PHP 5.3.0
			$supported_funcs = $cls::__funcs();
			//TODO PHP 5.2.6
			//$supported_funcs = call_user_func_array(array($cls, '__funcs'), null);
			$this->_rpcServer->add($supported_funcs, new $cls());
		}
	}

  public static function loadAPIFile($cls)
  {
  	$api = $cls;
    if (strcasecmp(substr($cls, -11), '_Controller') == 0 && strcasecmp(substr($cls, 0, 4), 'Api_') == 0) {
      $api = substr($cls, 4, -11);
    }
    $filename = trim(strtolower(strtr($api, '_', '/')), '/') . '.php';
    if (is_file(SITESPATH . '/default/controllers/api/' . $filename)) {
      require_once SITESPATH . '/default/controllers/api/' . $filename;
    }
  }
}