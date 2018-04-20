<?php
require_once("api_common.php");

class Api_User_Controller extends Api_Model
{
  private $_userInstance;

  public static function __funcs()
  {
    return array(
      'inc',
      'login',
      'loginout',
    );
  }

  public function init()
  {
    $this->_userInstance = User_Model::getInstance();
  }
  
  public function inc()
  {
    return 'aaaaa';
  }
  
  public function login($name, $pwd)
  {
  	/*
    if (!$uid = $this->_userInstance->validate(trim($name), $pwd)) {
      return PHPRPC_Authentication(0, 'Username or Password is invalid');
    } else {
      $this->_userInstance->setLogin($uid);
      return PHPRPC_Authentication($uid, 'login success');
    }
    */
  	return 'logged';
  }
  
  public function loginout()
  {
    if ($this->_userInstance->logged()) {
      $this->_userInstance->setLogout();
    }
  }
}

