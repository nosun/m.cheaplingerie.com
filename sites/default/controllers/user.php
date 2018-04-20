<?php
class User_Controller extends Bl_Controller
{
    
  private function redirectToReffer()
  {
    if (isset($_SESSION['redirect_url'])){
        gotoUrl($_SESSION['redirect_url']);
    }
    gotoUrl('');
  }
  
  private function thirdpartyLogin($loginType)
  {
      $result = array();
      if (empty($_POST))
      {
          $result['success'] = false;
          $result['error'] = 'data error';
      }
      else
      {
          if (empty($_POST['id']))
          {
              $result['success'] = false;
              $result['error'] = 'user id empty';
          }
          else if (empty($_POST['name']))
          {
              $result['success'] = false;
              $result['error'] = 'user name empty';
          }
          else if (empty($_POST['email']))
          {
              $result['success'] = false;
              $result['error'] = 'user email empty';
          }
          else
          {
              $post = $_POST;
              $userInstance = User_Model::getInstance();
              $userInfo = $userInstance->getThirdPartyUserInfo($post['email'], $post['id'], $loginType);
              if (!$userInfo)
              {
                  $post['thirdparty_id'] = $post['id'];
                  $post['type'] = $loginType;
                  $uid = $userInstance->insertUser($post, array());
                  if (!$uid)
                  {
                      $result['success'] = false;
                      $result['error'] = 'failed to login.';
                  }
                  else
                  {
                      $userInstance->setLogin($uid);
                      $result['success'] = true;
                      $result['name'] = $post['name'];
                  }
              }
              else 
              {
                  $userInstance->setLogin($userInfo->uid);
                  $result['success'] = true;
                  $result['name'] = $userInfo->name;
              }
          }
      }
      echo json_encode($result);
  }
  public function ajaxFacebookLoginAction()
  {
      $this->thirdpartyLogin(User_Model::USER_TYPE_FACEBOOK);
  }
  
  public function ajaxGoogleLoginAction()
  {
     $this->thirdpartyLogin(User_Model::USER_TYPE_GOOGLE);
  }
  
  public function loginAction()
  {
    global $user;
    $sid = $user->sid;
    $userInstance = User_Model::getInstance();
    if ($userInstance->logged()) {
      if(isset($_SERVER["HTTP_REFERER"]) && $_SERVER["HTTP_REFERER"]){
        header("Location: ".$_SERVER["HTTP_REFERER"]);
      }else{
        gotoUrl('');
      }
    }
    if ($this->isPost()) {
      if (!isset($_POST['username']) || strlen(trim($_POST['username'])) < 3) {
        setMessage(t('Username must be at least 3 characters long.'), 'error');
        gotoUrl('user/login');
      }
      //test whether the given username is an email address.
      $isEmail = $userInstance->isValidEmail($_POST['username']);
      if (!$uid = $userInstance->validate(trim($_POST['username']), $_POST['password'], $isEmail)) {
        setMessage(t('Username or Password is invalid'), 'error');
        gotoUrl('user/login');
      } else {
        $userInstance->setLogin($uid);
        /*setMessage(t('Landing successful'));*/
        log::save('user', trim($_POST['username']) . ' login.');
        $this->redirectToReffer();
        /*
        $reffer_url = key_exists('login_redirectUrl', $_SESSION)?$_SESSION['login_redirectUrl']:null;
        unset($_SESSION['login_redirectUrl']);
        if((isset($reffer_url) && strtolower($reffer_url) == url('user/getpassword'))||
        (isset($_SERVER["HTTP_REFERER"]) && strtolower($_SERVER["HTTP_REFERER"]) == url('user/getpassword'))){
        	gotoUrl('');
        }if (isset($reffer_url) && $reffer_url && strtolower($reffer_url) !== url('user/login')) {
          header("Location: ".$reffer_url);
        } else if(isset($_SERVER["HTTP_REFERER"]) && strtolower($_SERVER["HTTP_REFERER"]) !== url('user/login')){
          header("Location: ".$reffer_url);
        }else{
          gotoUrl('');
        }*/
      }
    } else {
      $this->view->render('login.phtml');
//       $this->view->render('testpage.phtml');
    }
  }
  
  public function logoutAction()
  {
    $userInstance = User_Model::getInstance();
    if ($userInstance->logged()) {
      $userInstance->setLogout();
    }
    gotoUrl('user/login');
  }

  public function registerAction()
  {
    $userInstance = User_Model::getInstance();
    $siteInstance = Site_Model::getInstance();
    if ($userInstance->logged()) {
      gotoUrl('');
    }
    if ($this->isPost()) {
      $invalidUsername = array(
        'admin',
        'administrator',
      );
      $post = $_POST;
      $postnew = callFunction('register', 'before', $post);
      if (isset($postnew) && $postnew) {
        $post = $postnew;
      }
      if (!isset($post['username']) || strlen(trim($post['username'])) < 3) {
        setMessage(t('Username must be at least 3 characters long.'), 'error');
      } else if (in_array(trim($post['username']), $invalidUsername)) {
        setMessage(t('Username is invalid.'), 'error');
      } else if ($userInstance->getUserInfoByName(trim($post['username']))) {
        setMessage(t('An account already exists.'), 'error');
      } else if (!isset($post['password']) || strlen($post['password']) < 5) {
        setMessage(t('Password must be at least 5 characters long.'), 'error');
      } else if (!isset($post['confirm_password']) || $post['password'] !== $post['confirm_password']) {
        setMessage(t('The passwords you have entered do not match. Please try again.'), 'error');
      } else if (!isset($post['email']) || false !== strpos($post['email'], '..') 
        || !preg_match('/^.{1,64}@[^@]{1,255}$/', trim($post['email']))) {
        setMessage(t('This email address is invalid.'), 'error');
      } else if ($userInstance->isValidEmail($post['email'])){
        setMessage(t('This email address is already registered. Please use another email account.'), 'error');
      }
      else {
        $post['name'] = trim($post['username']);
        $post['passwd'] = trim($post['password']);
        $roles = array();
        $uid = $userInstance->insertUser($post, $roles);
        if ($uid) {
          $userInstance->setLogin($uid);
          $_SESSION['from_register'] = 1;
          widgetCallFunctionAll('register');
          //发送用户注册邮件
          $emailSetting = Bl_Config::get('userRegisterEmail');
          if (isset($emailSetting) && $emailSetting['status']) {
            $stmpSetting = Bl_Config::get('stmp');
            if (isset($stmpSetting) && $stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd'] && $post['email']) {
              $mailInstance = new Mail_Model($stmpSetting);
              $email[] = $post['email'];
              $siteInfo = Bl_Config::get('siteInfo', array());
              if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
              	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          	  }
          	   $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title']);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content']);
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
                setMessage('Success, please check your mail');
              } else {
                setMessage('Met error when sending mail, you may not get the email for register.', 'error');
              }
            } else {
              setMessage('Mail server information is not configured properly, please check', 'error');
            }
          }
          if (isset($_SESSION['redirect_url'])) {
            $this->redirectToReffer();
          }else {
            gotoUrl('user/registersucc');
          }
        } else {
          setMessage(t('Register fail.'), 'error');
        }
      }
    }
    
    $countries = $siteInstance->getCountries();
    $cid = key($countries);
    $provinces = $siteInstance->getProvinces($cid);
    $this->view->render('register.phtml', array(
      'countries' => isset($countries) ? $countries : array(),
      'provinces' => isset($provinces) ? $provinces : array(),
    ));
  }

  public function registersuccAction()
  {
  	if(!isset($_SESSION['from_register'])){
  		gotoUrl('');
  	}
  	unset($_SESSION['from_register']);
  	if(isset($_SESSION['redirect_url'])){
  		gotoUrl($_SESSION['redirect_url']);
  	}
  	else{
  		gotoUrl('');
  	}
  }

  public function infoAction()
  {
    global $user;
    $userInstance = User_Model::getInstance();
    $siteInstance = Site_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    if ($this->isPost()) {
      $post = $_POST;
      
      if (!isset($post['email']) 
          || false !== strpos($post['email'], '..') 
          || !preg_match('/^.{1,64}@[^@]{1,255}$/', trim($post['email']))) {
          setMessage(t('This email address is invalid.'), 'error');
      }else{
        isset($post['cid']) ? $set['cid'] = $post['cid'] : 0;
        isset($post['pid']) ? $set['pid'] = $post['pid'] : 0;
        isset($post['country']) ? $set['country'] = $post['country'] : '';
        isset($post['province']) ? $set['province'] = $post['province'] : '';
        isset($post['city']) ? $set['city'] = $post['city'] : '';
        isset($post['nickname']) ? $set['nickname'] = $post['nickname'] : '';
        isset($post['email']) ? $set['email'] = $post['email'] : '';
        isset($post['phone']) ? $set['phone'] = $post['phone'] : '';
        isset($post['mobile']) ? $set['mobile'] = $post['mobile'] : '';
        isset($post['gender']) ? $set['gender'] = $post['gender'] : '';
        isset($post['postcode']) ? $set['postcode'] = $post['postcode'] : '';
        isset($post['area']) ? $set['area'] = $post['area'] : '';
        isset($post['data']) ? $set['data'] = $post['data'] : '';
        if (trim($post['birthday']) != '' && preg_match('/^(\w{4})[-\/](\w{1,2})[-\/](\w{1,2})$/', trim($post['birthday']), $matches)) {
          $set['birthday'] = $matches[1] . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        } else {
          $set['birthday'] = '';
        }
        $result = $userInstance->updateUser($user->uid, $set);
        if (!empty($post['email']) && $post['email'] != $user->email) {
            $delivery_record_count = $userInstance->getDeliveryRecordCount($user->uid);
            if ($delivery_record_count > 0) {
                $userInstance->updateDeliveryRecordEmail($post['email'], $user->uid);
            }
        }
        if ($result) {
          setMessage(t('Modify success'));
        } else {
          setMessage(t('Modified failure'));
        }
      }
      gotoUrl('user/info');
    } else {
      $countries = $siteInstance->getCountries();
      $cid = isset($user->cid) ? $user->cid : key($countries);
      $provinces = $siteInstance->getProvinces($cid);
      $user = $userInstance->getUserInfo($user->uid);
      $this->view->assign('tmark', 'info');
      $this->view->assign('templatefile', 'u_userinfo.phtml');
      $this->view->render('personalcenter.phtml', array(
        'user' => isset($user) ? $user : null,
        'countries' => isset($countries) ? $countries : array(),
        'provinces' => isset($provinces) ? $provinces : array(),
      ));
    }
  }

  public function pwdAction()
  {
    global $user;
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    if ($this->isPost()) {
      $post = $_POST;
      if ($post['newpwd'] != $post['newpwd2']){
        setMessage(t('The two passwords are not equal'));
      } else {
        if ($uid = $userInstance->validate($user->name, $post['oldpwd'])) {
          $result = $userInstance->updateUser($user->uid, array('passwd' => $post['newpwd']));
          if ($result) {
            setMessage(t('Modify success'));
          } else {
            setMessage(t('Modified failure'));
          }
        } else {
          setMessage(t('The old password mistake'));
        }
      }
      gotoUrl('user/pwd');

    } else {
      $this->view->assign('tmark', 'info');
      $this->view->assign('templatefile', 'u_userpwd.phtml');
      $this->view->render('personalcenter.phtml', array(
        'user' => isset($user) ? $user : null,
      ));
    }
  }

  public function addresslistAction()
  {
    global $user;
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    $addressList = $userInstance->getDeliveryRecordList($user->uid);
    $countries = Site_Model::getInstance()->getCountries();
    $cidlist = array_keys($countries);
    $provinces = Site_Model::getInstance()->getProvinces($cidlist['0']);
    $provinceList = array();
    foreach ($addressList as $address) {
        if ($address->delivery_pid != 0) {
            $provinceList[$address->rid] = $provinces = Site_Model::getInstance()->getProvinces($address->delivery_cid);
        }
    }
    $this->view->assign('templatejs', themeResourceURI('javascripts/manage_address.js'));
    $this->view->assign('tmark', 'address');
    $this->view->assign('templatefile', 'u_addresslist.phtml');
    $this->view->render('personalcenter.phtml', array(
      'addressList' => isset($addressList) ? $addressList : null,
      'countries' =>  $countries,
      'provinces' => $provinces,
      'provinceList' => $provinceList,
    ));
  }

  public function editaddressAction($rid = null)
  {
    global $user;
    $userInstance = User_Model::getInstance();
    $siteInstance = Site_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    if ($this->isPost()) {
      $post = $_POST;
      /*
      $set = array(
        'delivery_first_name' => $post['delivery_first_name'],
        'delivery_last_name' => $post['delivery_last_name'],
        'delivery_email' => $post['delivery_email'],
        'delivery_phone' => $post['delivery_phone'],
        'delivery_mobile' => $post['delivery_mobile'],
        'delivery_city' => $post['delivery_city'],
        'delivery_cid' => $post['delivery_cid'],
        'delivery_pid' => $post['delivery_pid'],
        'delivery_address' => $post['delivery_address'],
        'delivery_postcode' => $post['delivery_postcode'],
        'delivery_time' => $post['delivery_time'],
        'default' => isset($post['default']) ? 1 : 0,
      );
      list($set['delivery_country'], $set['delivery_province']) = $siteInstance->getCountryProvincesNames($post['delivery_cid'], $post['delivery_pid']);
    	if(empty($set['delivery_province']) && $post['delivery_or_province'])
      {
      	$set['delivery_province'] = $post['delivery_or_province'];
      }*/
      if ($post['rid']) {
        $status = $userInstance->updateDeliveryRecord($post['rid'], $set);
          if ($status) {
//             setMessage(t('The address was successfully updated.'));
          } elseif ($status == 0) {
//             setMessage(t('No updates.'));
          } else {
//             setMessage(t('Updating address information failed.'));
          }
      } else {
          if ($this->addAddress($post, $error)) {
//               setMessage(t('The address was successfully updated.'));
          } else {
//               setMessage($error, 'error');
              if($error){
              	gotoUrl('user/newaddress');
              }
          }
      }
      if(isset($_SESSION['newaddressfrom']) && $_SESSION['newaddressfrom']){
      	$origin = $_SESSION['newaddressfrom'];
      	$origin = str_replace("//", "/", $origin);
      	$sths = explode("/", $origin);
      	$url = $sths[2]."/".$sths[3]."/".$sths[4];
      	gotoUrl($url);
      }
      if(isset($_SESSION['editaddressfrom']) && $_SESSION['editaddressfrom']){
      	$origin = $_SESSION['editaddressfrom'];
      	$origin = str_replace("//", "/", $origin);
      	$sths = explode("/", $origin);
      	$url = $sths[2]."/".$sths[3]."/".$sths[4];
      	gotoUrl($url);
      }
      else{
      	gotoUrl('user/addresslist');
      }
    }
  }

  public function ajaxAddAddressAction()
  {
      global $user;
      $result = array(
          "success" => false,
          "action" => "",
          "data" => "");
      $userInstance = User_Model::getInstance();
      $siteInstance = Site_Model::getInstance();
      if (!$userInstance->logged())
      {
          $result["success"] = false;
          $result["action"] = "window.location=" . url("user/login");
      }
      else
      {
          $post = $_POST;
          if (!$this->addAddress($post, $error))
          {
              $result["success"] = false;
              $result["data"] = $error;
          }
          else
          {
              $result["success"] = true;
              $result["data"] = $userInstance->getDeliveryRecordList($user->uid);
          }
      } 
      
      echo json_encode($result);
  }
  
public function ajaxUpdateAddressAction()
  {
      global $user;
      $result = array(
          "success" => false,
          "action" => "",
          "data" => "");
      $userInstance = User_Model::getInstance();
      $siteInstance = Site_Model::getInstance();
      if (!$userInstance->logged())
      {
          $result["success"] = false;
          $result["action"] = "window.location=" . url("user/login");
      }
      else
      {
          $post = $_POST;
          if (!$this->updateAddress($post, $error))
          {
              $result["success"] = false;
              $result["data"] = $error;
          }
          else
          {
              $result["success"] = true;
              $result["data"] = $userInstance->getDeliveryRecordList($user->uid);
          }
      } 
      
      echo json_encode($result);
  }

  public function parsePostData($post, &$set, $rid=null)
  {
      global $user;
      $suffix = "";
      if (isset($rid))
      {
          $suffix = "_" . $rid;
      }
      $set = array(
        'delivery_first_name' => $post['delivery_first_name' . $suffix],
        'delivery_last_name' => $post['delivery_last_name' . $suffix],
        'delivery_email' => $user->email,
        //'delivery_phone' => $post['delivery_phone'],
        'delivery_mobile' => $post['delivery_mobile' . $suffix],
        'delivery_city' => $post['delivery_city' . $suffix],
        'delivery_address' => $post['delivery_address' . $suffix],
        'delivery_postcode' => $post['delivery_postcode' . $suffix],
        //'delivery_time' => $post['delivery_time'],
        'default' => isset($post['default' . $suffix]) && (int)$post['default' . $suffix] > 0 ? 1 : 0,
      );
      $cid = isset($post['delivery_cid' . $suffix]) ? $post['delivery_cid' . $suffix] : $post['delivery_cid_select' . $suffix];
      $set['delivery_cid'] = $cid;
      $pid = isset($post['delivery_pid' . $suffix]) ? $post['delivery_pid' . $suffix] : $post['delivery_pid_select' . $suffix];
      $set['delivery_pid'] = $pid;
      list($set['delivery_country'], $set['delivery_province']) = Site_Model::getInstance()->getCountryProvincesNames($set['delivery_cid'], $set['delivery_pid']);
    	if(empty($set['delivery_province']))
      {
      	$set['delivery_province'] = $post['delivery_province' . $suffix];
      }
  }
  
  public function addAddress($post, &$error)
  {
      if (!$this->checkAddress($post, null, $error))
      {
          return false;
      }
      $this->parsePostData($post, $set);
      $status = User_Model::getInstance()->insertDeliveryRecord($set);
      if (!$status)
      {
          $error = t('insert shipping address failed.');
          return false;
      }
      return true;
  }
  
  public function updateAddress($post, &$error)
  {
      if (!$this->checkAddress($post, null, $error))
      {
          return false;
      } 
      if (isset($post['delivery_rid']) && ($rid = (int)$post['delivery_rid']) <= 0)
      {
          return false;
      }
      $this->parsePostData($post, $set);
      if (!User_Model::getInstance()->updateDeliveryRecord($rid, $set))
      {
          $error = t('update address failed');
          return false;
      }
      return true;
  }
  public function checkAddress($post, $rid = null, &$error = null)
  {
      $suffix = "";
      if (isset($rid))
      {
          $suffix = "_" . $rid;
      }
      if (!isset($post['delivery_first_name' . $suffix]) || trim($post['delivery_first_name' . $suffix]) == "")
      {
          $error = t('first name is empty');
          return false;
      }
      if (!isset($post['delivery_last_name' . $suffix]) || trim($post['delivery_last_name' . $suffix]) == "")
      {
          $error = t('last name is empty');
          return false;
      }
      if (!isset($post['delivery_address' . $suffix]) || trim($post['delivery_address' . $suffix]) == "")
      {
          $error = t('address is empty');
          return false;
      }
      if (!isset($post['delivery_city' . $suffix]) || trim($post['delivery_city' . $suffix]) == "")
      {
          $error = t('city is empty');
          return false;
      }
      if ((!isset($post['delivery_province' . $suffix]) || trim($post['delivery_province' . $suffix]) == "") && 
          (!isset($post['delivery_pid' . $suffix]) || (int)$post['delivery_pid' . $suffix] <= 0) && 
          (!isset($post['delivery_pid_select' . $suffix]) || (int)$post['delivery_pid_select' . $suffix] <= 0))
      {
          $error = t('province is empty');
          return false;
      }
      if ((!isset($post['delivery_cid' . $suffix]) || trim($post['delivery_cid' . $suffix]) == "") &&
            (!isset($post['delivery_cid_select' . $suffix]) || trim($post['delivery_cid_select' . $suffix]) == ""))
      {
          $error = t('country is empty');
          return false;
      }
      if (!isset($post['delivery_mobile' . $suffix]) || trim($post['delivery_mobile' . $suffix]) == "")
      {
          $error = t('phone number is empty');
          return false;
      }
      return true;
  }
  
  public function updateAddressAction($rid = null)
  {
      if (!isset($rid))
      {
          gotourl('user/addresslist');
      }
      if ($this->isPost()) {
          $post = $_POST;
          if (!$this->checkAddress($post, $rid, $error))
          {
              setMessage($error, "error");
              gotourl('user/addresslist');
          }
          $this->parsePostData($post, $set, $rid);
          if (!User_Model::getInstance()->updateDeliveryRecord($rid, $set))
          {
              setMessage(t('update address failed'), 'error');
          }
          else
          {
              setMessage(t('update address success'), 'notice');
          }
          gotourl('user/addresslist');
      }
  }
  public function deleteaddressAction($rid)
  {
    global $user;
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    $fitter = array('uid' => $user->uid);
    if (!$userInstance->getDeliveryRecordInfo($rid, $fitter)) {
      goto404(t('Delete failure'));
    }
    if ($userInstance->deleteDeliveryRecord($rid, $user->uid)) {
      setMessage(t('Delete success'));
    } else {
      setMessage(t('Delete failure') , 'error');
    }
    gotoUrl('user/myaddressbook');
  }

  public function ajaxgetaddressAction($rid)
  {
    $userInstance = User_Model::getInstance();
    $addressInfo = $userInstance->getDeliveryRecordInfo($rid);
    echo json_encode($addressInfo);
  }

  public function getPasswordAction()
  {
    if ($this->isPost()) {
      $username = isset($_POST['username']) ? $_POST['username'] : null;
      $email = isset($_POST['email']) ? $_POST['email'] : null;
      if($this->_getPasswordSendMail($email)){
      	$this->view->render('getpassword_success.phtml');
      }else{
      	setMessage(t('We’re sorry. we weren’t able to identify you by the information provided.'), 'error');
      	gotoUrl('user/getPassword');
      }
      
//       gotoUrl('user/login');
    } else {
      $this->view->render('getpassword.phtml');
    }
  }

  private function _getPasswordSendMail($email)
  {
    $userInstance = User_Model::getInstance();
    $userId = $userInstance->isValidEmail($email);
    if(!$userId){
//       setMessage(t('No user matches the email provided.'), 'error');
      return false;
    }
    $userInfo = $userInstance->getUserInfo($userId);
    $username = $userInfo->name;
    if (!isset($userInfo) || !$userInfo) {
//       setMessage(t('No user matches the email provided.'), 'error');
      return false;
    }
    if ($userInfo->email != $email) {
//       setMessage(t('Input mailbox and register mailbox inconsistent'), 'error');
      return false;
    }
    $emailSetting = Bl_Config::get('getPasswordEmail');
    if (!isset($emailSetting) || !$emailSetting['status']) {
      
    }
    $stmpSetting = Bl_Config::get('stmp');
    if (!isset($stmpSetting) || !$stmpSetting['stmpserver']
    || !$stmpSetting['stmpuser'] || !$stmpSetting['stmppasswd']) {
      setMessage(t('Mail server information is not configured properly, please check'), 'error');
      return false;
    }
    $token = randomString(16);
    $time = time();
    $data = array(
      'token' => $token,
      'time' => $time,
    );
    if(!$userInstance->updateUser($userInfo->uid, array('data' => $data))) {
//       setMessage(t('Database error'), 'error');
      return false;
    }
    $mailInstance = new Mail_Model($stmpSetting);
    $url = url('user/editpassword/' . $username . '/' . $token);
    $str = '<a href="' . $url . '"> Click here to change the password!</a>';
    $emailSetting['content'] = str_replace('{[url]}', $str, $emailSetting['content']);
    $emails[] = $email;
    $siteInfo = Bl_Config::get('siteInfo', array());

    if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
        $emails[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
    }
    $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], null, $userId);
    $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], null, $userId);
    $title = $emailSetting['title'];
    $content =  $emailSetting['content'];
    if (!$mailInstance->sendMail($emails, $title, $content, 'html')) {
//       setMessage(t('send mail error'), 'error');
      return false;
    }
//     setMessage(t('We have sent an email to '. $email.', please check for next step.'));
    return true;
  }

  public function editPasswordAction($username = null, $token = null)
  {
    if ($this->isPost()) {
      $username = isset($_POST['username']) ? $_POST['username'] : null;
      $token = isset($_POST['token']) ? $_POST['token'] : null;
      $newpwd = isset($_POST['newpwd']) ? $_POST['newpwd'] : null;
      $newpwd2 = isset($_POST['newpwd2']) ? $_POST['newpwd2'] : null;
      if (!isset($newpwd) || !isset($newpwd2) || $newpwd != $newpwd2) {
        setMessage(t('2 times the password'));
      } elseif (strlen($newpwd)< 5 || strlen($newpwd)> 12) {
        setMessage(t('Password must be greater than 5 persons, and less than 12'), 'error');
      }else {
        if (isset($username) && $username && isset($token) && $token) {
          $userInstance = User_Model::getInstance();
          $userInfo = $userInstance->getUserInfoByName($username);
          if (!isset($userInfo)) {
              setMessage(t('This user no longer exists'), 'error');
          } else {
            $datatoken = isset($userInfo->data['token']) ? $userInfo->data['token'] : null;
            $time = time();
            if (isset($userInfo->data['time']) && $userInfo->data['time'] > ($time - 3600*24)) {
              if (isset($datatoken) && $datatoken == $token) {
                $result = $userInstance->updateUser($userInfo->uid, array('passwd' => $newpwd));
                if ($result) {
                  setMessage(t('Modify success'));
                  gotoUrl('user/login');
                } else {
                  setMessage(t('Modified failure'));
                }
              } else {
                setMessage(t('Input mailbox and register mailbox inconsistent'), 'error');
              }
            } else {
              setMessage(t('Token overtime'), 'error');
            }
          }
        } else {
          setMessage(t('error'), 'error');
        }
      }
      gotoUrl('user/editpassword/' . $username . '/' . $token);
    } else {
      if (!isset($username)) {
        goto404();
      }
      $this->view->render('editpassword.phtml', array(
        'username' => $username,
        'token' => $token,
      ));
    }
  }
  
  public function getUserIntegralAction()
  {
  	$userInstance = User_Model::getInstance();
  	$integral = $userInstance->getUserIntegral();
  	return $integral ? $integral : '0';
  }
  
  
  
  
  //  以下是移动端新添加
  public function newaddressAction(){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	
  	$countries = Site_Model::getInstance()->getCountries();
  	$cidlist = array_keys($countries);
  	$provinces = Site_Model::getInstance()->getProvinces($cidlist['0']);
  	
  	$backurl = isset($_SESSION['newaddressfrom']) ? $_SESSION['newaddressfrom'] : null;
  	
  	$this->view->setTitle("Add New Address");
  	
  	$this->view->render('user_newaddress.phtml', array(
  			'countries' =>  $countries,
  			'provinces' => $provinces,
  			'user' => $user,
  			'backurl' => $backurl,
  	));
  }
  
  
  
  
  public function myaccountAction($page = 1, $status = null){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	
  	if($this->isPost()){
  		unset($_SESSION['ordersearch']);
  		$post = $_POST;
  		$filter = $post;
  		if(isset($post['posttype']) && $post['posttype'] == "ordersearch"){
  			array_shift($post);
  			foreach ($post as $k => $v){
  				$_SESSION['ordersearch'][$k] = $v;
  			}
  			
  		}
  	}
  	else{
//   		unset($_SESSION['ordersearch']);
  	}
  	if(isset($_SESSION['ordersearch'])){
  		$filter = $_SESSION['ordersearch'];
  	}

  	$siteInstance = Site_Model::getInstance();
  	$orderInstance = Order_Model::getInstance();
  	$paymentInstance = Payment_Model::getInstance();
  	$paymentList = $paymentInstance->getPaymentsList();
  	
  	$addressList = $userInstance->getDeliveryRecordList($user->uid);
  	
  	$pageRows = 3;
  	$filter['uid'] = $user->uid;
  	$ordersList = $orderInstance->getOrdersList($filter, $page, $pageRows);
  	$orderscount = $orderInstance->getOrdersCount($filter);
  	foreach ($ordersList as $k => $v){
  		$orderInfo = $orderInstance->getOrderInfo($v->oid);
  		$v->firstitem = reset($orderInfo->items);
  		$a = 0;
  	}
  	$orderscount = $orderInstance->getOrdersCount($filter);
  	
  	if ($page == 1) {
  		$_SESSION['FirstPath']['orderList'] = trim($_SERVER['REQUEST_URI'], '/');
  	}
  	$firstPath = isset($_SESSION['FirstPath']['orderList']) ? $_SESSION['FirstPath']['orderList'] : null;
  	$urlpage = 'user/myaccount/';
  	
  	$this->view->setTitle("Adoringdress Account Center");
  	
  	$this->view->render('user_myaccount.phtml', array(
  			'user' => $user,
//   			'ordersList' => $ordersList,
			'lastorder' => array_shift($ordersList),
  			'paymentList' => $paymentList,
  			'addressList' => isset($addressList) ? $addressList : null,
  			'filter' => isset($filter) ? $filter : null,
//   			'urlPage' => $urlpage,
//   			'pagination' => callFunction('common_pagination', 'user/myaccount/%d/' . $status, ceil($orderscount/$pageRows), $page, $firstPath),
//   			'pagination' => callFunction('combo_pagination', '/user/myaccount/%d/', ceil($orderscount/$pageRows), $page),
  	));
  }
  
  public function ajaxconfirmandchangepwdAction(){
  	if($this->isPost()){
  		global $user;
  		$userInstance = User_Model::getInstance();
  		if (!$userInstance->logged()) {
  			gotoUrl('user/login');
  		}
  		
  		$post = $_POST;
  		$name = $post['username'];
  		$pwd = $post['oldpwd'];
  		$newpwd = $post['newpwd'];
  		 
  		$userInstance = User_Model::getInstance();
  		$uid = $userInstance->validate($name, $pwd);
  		if($uid){
  			$result = $userInstance->updateUser($user->uid, array('passwd' => $newpwd));
  			if($result){
  				echo "success";
  			}
  		}else{
  			echo "fail";
  		}
  	}
  	else{
  		goto404();
  	}
  }
  
  public function mobilechangepwdAction(){
  	if($this->isPost()){
  		global $user;
  		$userInstance = User_Model::getInstance();
  		if (!$userInstance->logged()) {
  			gotoUrl('user/login');
  		}
  		
  		$post = $_POST;
  		$name = $post['username'];
  		$pwd = $post['oldpwd'];
  		$newpwd = $post['newpwd'];
  		$renewpwd = $post['renewpwd'];
  		 
  		$userInstance = User_Model::getInstance();
  		$uid = $userInstance->validate($name, $pwd);
  		if($uid){
  			if($newpwd != $renewpwd){
  				setMessage(t('The two passwords are not equal'), 'error');
  				gotoUrl("user/changepwd");
  			}
  			$result = $userInstance->updateUser($user->uid, array('passwd' => $newpwd));
  			if($result){
  				gotoUrl("user/logout");
  			}
  		}else{
  			setMessage(t('Your old password is wrong.'), 'error');
  			gotoUrl("user/changepwd");
  		}
  	}
  	else{
  		goto404();
  	}
  }
  
  public function mobiledeleteAddressAction($rid){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	
  	$backurl = $_SERVER['HTTP_REFERER'];
  	
  	$fitter = array('uid' => $user->uid);
  	if (!$userInstance->getDeliveryRecordInfo($rid, $fitter)) {
  		goto404(t('Delete failure'));
  	}
  	if ($userInstance->deleteDeliveryRecord($rid, $user->uid)) {
//   		setMessage(t('Delete success'));
  	} else {
//   		setMessage(t('Delete failure'), 'error');
  	}
  	$items = substr($backurl, strpos($backurl, 'checkout/') + 9);
  	gotoUrl('cart/mobilecheckout/' . $items);
  }
  
  
  public function userinfoeditAction(){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	
  	if($this->isPost()){
  		$post = $_POST;
  		isset($post['cid']) ? $set['cid'] = $post['cid'] : 0;
  		isset($post['pid']) ? $set['pid'] = $post['pid'] : 0;
  		isset($post['country']) ? $set['country'] = $post['country'] : '';
  		isset($post['province']) ? $set['province'] = $post['province'] : '';
  		isset($post['city']) ? $set['city'] = $post['city'] : '';
  		isset($post['nickname']) ? $set['nickname'] = $post['nickname'] : '';
  		isset($post['email']) ? $set['email'] = $post['email'] : '';
  		isset($post['phone']) ? $set['phone'] = $post['phone'] : '';
  		isset($post['mobile']) ? $set['mobile'] = $post['mobile'] : '';
  		isset($post['gender']) ? $set['gender'] = $post['gender'] : '';
  		isset($post['postcode']) ? $set['postcode'] = $post['postcode'] : '';
  		isset($post['area']) ? $set['area'] = $post['area'] : '';
  		isset($post['data']) ? $set['data'] = $post['data'] : '';
  		isset($post['birthday']) ?  $set['birthday'] = $post['birthday'] : '';
  		
  		$errmsg = '';
/*  		if(!$set['nickname']){
  			$errmsg = 'Please enter your nickname';
  		}*/
  		if(!$set['email']){
  			$errmsg = 'Please enter your email';
  		}
/*  		if(!$set['birthday']){
  			$errmsg = 'Please select your birthday';
  		}*/
  		if($errmsg){
  			setMessage(t($errmsg), 'error');
  			gotoUrl("user/userinfoedit");
  		}
  		
  		$result = $userInstance->updateUser($user->uid, $set);
  		$changeflag = false;
  		if ($result) {
  			$changeflag = true;
  		} else {
  			$changeflag = false;
  			setMessage(t('Personal Information Modify Failure'), 'error');
  			gotoUrl("user/userinfoedit");
  		}
  	}
  	
  	global $user;
  	
  	$this->view->setTitle("Edit Personal Information");
  	
	$this->view->render('user_editinfo.phtml',array(
		'user' => $user,
		'changeflag' => isset($changeflag) ? $changeflag : null,
	));
  }
  
  
  public function useraddresseditAction($rid){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	$rid = isset($_POST['rid']) ? $_POST['rid'] : $rid; 
  	$address = $userInstance->getDeliveryRecordInfo($rid);
  	if (!$address) {
  		goto404(t('No address'));
  	}
  	
  	if($address->uid == $user->uid){
  		if($this->isPost()){
  			$post = $_POST;
  		
  			if ($post['rid']) {
  				array_shift($post);
  				$set = $post;
  				$set['delivery_email'] = $user->email;
  				$status = $userInstance->updateDeliveryRecord($rid, $set);
  				if ($status) {
  					if(isset($_SESSION['editaddressfrom']) && $_SESSION['editaddressfrom']){
  						$origin = $_SESSION['editaddressfrom'];
  						$origin = str_replace("//", "/", $origin);
  						$sths = explode("/", $origin);
  						$url = $sths[2]."/".$sths[3]."/".$sths[4];
  						gotoUrl($url);
  					}
  				} elseif ($status == 0) {
  					if(isset($_SESSION['editaddressfrom']) && $_SESSION['editaddressfrom']){
  						$origin = $_SESSION['editaddressfrom'];
  						$origin = str_replace("//", "/", $origin);
  						$sths = explode("/", $origin);
  						$url = $sths[2]."/".$sths[3]."/".$sths[4];
  						gotoUrl($url);
  					}
  				} else {
  					setMessage(t('Updating address information failed.'), 'error');
  				}
  			} else {
  				if ($this->addAddress($post, $error)) {
  					setMessage(t('The address was successfully updated.'));
  				} else {
  					setMessage($error, 'error');
  				}
  			}
  		}
  		else{
  			$_SESSION['editaddressfrom'] = $_SERVER['HTTP_REFERER'];
  		}
  		 
  		$backurl = isset($_SESSION['editaddressfrom']) ? $_SESSION['editaddressfrom'] : null;
  		 
  		$countries = Site_Model::getInstance()->getCountries();
  		$cidlist = array_keys($countries);
  		$provinces = Site_Model::getInstance()->getProvinces($cidlist['0']);
  		
  		$this->view->setTitle("Edit Address");
  		 
  		$this->view->render('user_editaddress.phtml', array(
  				'user' => $user,
  				'address' => $address,
  				'countries' =>  $countries,
  				'provinces' => $provinces,
  				'backurl' => $backurl,
  		));
  	}
  	else{
  		gotoUrl('');
  	}
  	
  }
  
  
  // wish list的功能，等待确认吧
  public function ajaxtoggleproducttowishlistAction(){
  	if($this->isPost()){
  		$post = $_POST;
  		global $user;
  		$userInstance = User_Model::getInstance();
  		$filter['pid'] = $post['pid'];
  		if ($userInstance->logged()) {
  			$filter['uid'] = $user->uid;
  			$result = $userInstance->toggleProductToWishList($filter);
  			echo $result;
  		}
  		else{
  			$result = json_encode(array('error' => 'needlogin'));
  			$product = Product_Model::getInstance()->getProductInfo($post['pid']);
  			$_SESSION['redirect_url'] = $product->url;
  		//  $_SESSION['redirect_url']
  			echo $result;
  		}
  	}
  }
  
  public function wishlistAction($page = 1){
  	global $user;
  	
  	if(!$user->uid){
  		gotoUrl('user/login');
  	}
  	
  	$wishlistInstance = WishList_Model::getInstance();
  	
  	$productlist = $wishlistInstance->getWishListInfoByUid($user->uid);

  	$cart_item_ids_array = array();
  	
  	$cartInfo = Cart_Model::getInstance()->getCartProductList();
  	$cartInfo_new = callFunction('cart', 'list', $cartInfo);
  	if (isset($cartInfo_new)) {
  		$cartInfo = $cartInfo_new;
  	}
  	
  	foreach ($cartInfo->product as $product){
  		$cart_item_ids_array[] = $product->cart_item_id;
  	}
  	
  	$cart_item_ids_str = implode(',', $cart_item_ids_array);
  	
  	$recommendProductList = Front_Model::getInstance()->getProductsListBySpecial(array('special_tid' =>20), 1, 9, true);
  	
  	$this->view->setTitle("Adoringdress Wishlist");
  	
  	$this->view->render('user_wishlist.phtml', array(
  		'productlist' => $productlist,
  		'recommandProductList' => $recommendProductList,
  		'cart_items' => $cart_item_ids_str,
  	));
  }
  
  public function deletefromwishlistAction($pid){
  	global $user;
  	$wishListInstance = WishList_Model::getInstance();
  	if (!$user->uid) {
  		gotoUrl('user/login');
  	}
  	
  	if($wishListInstance->deleteproductfromwishlist($pid)){
  		gotoUrl('user/wishlist');
  	}
  }
  
  public function customsupportAction(){
  	$backurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
  	$this->view->render('custom_support.phtml', array(
  			'backurl' => $backurl,
  	));
  }
  
  public function myordersAction($page = 1){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	
  	$siteInstance = Site_Model::getInstance();
  	$orderInstance = Order_Model::getInstance();
  	$paymentInstance = Payment_Model::getInstance();
  	$paymentList = $paymentInstance->getPaymentsList();
  	  	 
  	$filter['uid'] = $user->uid;
//   	$ordersList = $orderInstance->getOrdersList($filter, $page, $pageRows);
  	$ordersList = $orderInstance->getOrdersList($filter, 1, 3, true);
  	$orderscount = $orderInstance->getOrdersCount($filter);
  	foreach ($ordersList as $k => $v){
  		$order_items = $orderInstance->getOrderItems($v->oid);
  		$v->items = $order_items;
  	}
//   	$orderscount = $orderInstance->getOrdersCount($filter);
  	 
  	$this->view->setTitle("Adoringdress Orders List	");
  	
  	$this->view->render('user_myorder.phtml', array(
  			'user' => $user,
  			'ordersList' => $ordersList,
  	));
  }
  
  public function ajaxgetmoreordersAction(){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	
  	if($this->isPost()){
  		$post = $_POST;
  		$uid = $user->uid;
  		$page = $post['page'];
  		$ordersList = Order_Model::getInstance()->getOrdersList(array('uid' => $uid), $page, 3, true);
  		foreach ($ordersList as $k => $v){
  			$order_items = Order_Model::getInstance()->getOrderItems($v->oid);
  			$v->items = $order_items;
  		}
  		$this->view->render('ajax/ajaxgetmoreorders.phtml', array(
  				'user' => $user,
  				'ordersList' => $ordersList,
  		));
  	}
  }
  
  public function myaddressbookAction($page = 1){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	 
  	$siteInstance = Site_Model::getInstance();
  	$orderInstance = Order_Model::getInstance();
  	$paymentInstance = Payment_Model::getInstance();
  	$paymentList = $paymentInstance->getPaymentsList();
  	
  	$addressList = $userInstance->getDeliveryRecordList($user->uid);
  	 
  	$pageRows = 3;
  	$filter['uid'] = $user->uid;
  	$ordersList = $orderInstance->getOrdersList($filter, $page, $pageRows);
  	$orderscount = $orderInstance->getOrdersCount($filter);
  	foreach ($ordersList as $k => $v){
  		$orderInfo = $orderInstance->getOrderInfo($v->oid);
  		$v->firstitem = reset($orderInfo->items);
  		$a = 0;
  	}
  	$orderscount = $orderInstance->getOrdersCount($filter);
  
  	$urlpage = 'user/myorders/';
  	
  	$this->view->setTitle("Adoringdress Address Book");
  
  	$this->view->render('user_myaddressbook.phtml', array(
  			'user' => $user,
  			'addressList' => isset($addressList) ? $addressList : null,
//   			'urlPage' => $urlpage,
//   			'pagination' => callFunction('combo_pagination', '/user/myorders/%d/', ceil($orderscount/$pageRows), $page),
  	));
  }
  
  public function mysettingsAction($page = 1){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	 
  	$siteInstance = Site_Model::getInstance();
  	$orderInstance = Order_Model::getInstance();
  	$paymentInstance = Payment_Model::getInstance();
  	$paymentList = $paymentInstance->getPaymentsList();
  	 
  	$pageRows = 3;
  	$filter['uid'] = $user->uid;
  	$ordersList = $orderInstance->getOrdersList($filter, $page, $pageRows);
  	$orderscount = $orderInstance->getOrdersCount($filter);
  	foreach ($ordersList as $k => $v){
  		$orderInfo = $orderInstance->getOrderInfo($v->oid);
  		$v->firstitem = reset($orderInfo->items);
  		$a = 0;
  	}
  	$orderscount = $orderInstance->getOrdersCount($filter);
  
  	$urlpage = 'user/myorders/';
  
  	$this->view->render('user_mysetting.phtml', array(
  			'user' => $user,
  			//   			'ordersList' => $ordersList,
  			'ordersList' => $ordersList,
  			'paymentList' => $paymentList,
  			'addressList' => isset($addressList) ? $addressList : null,
  			'urlPage' => $urlpage,
  			//   			'pagination' => callFunction('common_pagination', 'user/myaccount/%d/' . $status, ceil($orderscount/$pageRows), $page, $firstPath),
  			'pagination' => callFunction('combo_pagination', '/user/myorders/%d/', ceil($orderscount/$pageRows), $page),
  	));
  }
  
  public function changepwdAction(){
  	global $user;
  	$userInstance = User_Model::getInstance();
  	if (!$userInstance->logged()) {
  		gotoUrl('user/login');
  	}
  	 
  	$this->view->setTitle("Change Password");
  	
  	$this->view->render('user_changepwd.phtml',array(
  			'user' => $user,
  	));
  }
  
  public function movewishtocartAction(){
  	if($this->isPost()){
  		$post = $_POST;
  		$wishid = $post['wishid'];
  		$qty = $post['qty'];
  		
  		$wishinfo = WishList_Model::getInstance()->getWishListItemInfoByWishId($wishid);
  		
  		$set = array(
  			'upid' => $wishid,
  			'uid' => $wishinfo[0]->uid,
  			'pid' => $wishinfo[0]->pid,
  			'qty' => $qty,
  			'data' => $wishinfo[0]->data,
  		);
  		
  		if( WishList_Model::getInstance()->moveWishToCart($set) ){
  			gotourl('user/wishlist');
  		}else{
  			
  		}
  	}
  }
  
}
