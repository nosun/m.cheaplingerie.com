<?php
class Mailsubscribe extends Widget_Abstract
{
  public function urls()
  {
    return array(
      'saveemailaddress',
      'cancelsubscribe',
      'cancelsubscribebyemail',
    );
  }

  /**
   * 保存ajax发送过来的email地址
   * @param object $instance
   */
  public function _saveemailaddress($instance, $email = '')
  {
    $json = array();

    if ($email=='') {
      $json['ERROR'] = 1;
      $json['MSG'] = t('Input your email address please.');
      exit(json_encode($json));
    }
    if (!preg_match('/^.{1,64}@[^@]{1,255}$/', $email)) {
      $json['ERROR'] = 2;
      $json['MSG'] = t('Invalid email address.');
      exit(json_encode($json));
    }
    if ($this->checkIfEmailExist($email)) {
      $json['ERROR'] = 3;
      $json['MSG'] = t('Email address already existed.');
      exit(json_encode($json));
    }
    $this->saveEmailAddressToDB($email);
    $json['ERROR'] = 0;
    $json['MSG'] = t('Subscribe successful.');
    exit(json_encode($json));
  }

  /**
   * 取消邮件订阅
   */
  public function _cancelsubscribe($instance, $key = '')
  {
    $cancel = $this->cancelSubscribeDB($key);
    $error = $cancel[0];
    $address = $cancel[1];
    if ($error==0) {
      $message = t('Cancel subscribe successful, email address is: ') . $address . '.';
    } else if($error==1) {
      $message = t('Had been cancel, email address is: ') . $address . '.';
    } else {
      $message = t('Email address does not exist.') ;
    }
      $instance->view->render('../plugins/widget/mailsubscribe/cancelsubscribe.phtml', array(
        'message' => $message,
      ));
  }

  /**
   * 根据Email取消邮件订阅
   */
  public function _cancelsubscribebyemail($instance, $email = '')
  {
    $cancel = $this->cancelSubscribeDBByEmail($email);
    $error = $cancel[0];
    $key = $cancel[1];
    if ($error==0) {
      $message = t('Cancel subscribe successful, email address is: ') . $email . '.';
    } else if($error==1) {
      $message = t('Had been cancel, email address is: ') . $email . '.';
    } else {
      $message = t('Email address does not exist.') ;
    }
      $json['ERROR'] = $error;
      $json['MSG'] = $message;
      $json['key'] = $key;
      exit(json_encode($json));
  }

  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $usersEmailList = $this->getUsersEmailList();
    $instance->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
    $instance->view->render('../plugins/widget/mailsubscribe/info.phtml', array(
      'usersEmailList' => $usersEmailList,
    ));
  }

  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    if (isset($_POST['MailUidList']) && $_POST['MailUidList'] != '') {
      $emailList = json_decode($_POST['MailUidList']);
    }else{
      setMessage(t('Please select at least one user.'), 'error');
      gotoUrl('admin/site/widgetedit/mailsubscribe');
    }
    if (isset($_POST['mailTitle']) && $_POST['mailTitle'] != '') {
      $mailTitle = $_POST['mailTitle'];
    }else{
      setMessage(t('Please input the email title.'), 'error');
      gotoUrl('admin/site/widgetedit/mailsubscribe');
    }
    if (isset($_POST['mailContent']) && $_POST['mailContent'] != '') {
      $mailContent = $_POST['mailContent'];
    }else{
      setMessage(t('Please input the email content.'), 'error');
      gotoUrl('admin/site/widgetedit/mailsubscribe');
    }
    if (is_array($emailList) && count($emailList)>0) {
      $this->doSendMailToUser($emailList, $mailTitle, $mailContent);
    }
    gotoUrl('admin/site/widgetedit/mailsubscribe');
  }

  /**
   * 安装插件
   */
  public function install()
  {
    $this->createMailsubscribeTable();
  }

  /**
   * 执行邮件的发送
   * @param String $nickname
   * @param String $emailAddress
   * @param String $mailContent
   */
  private function doSendMailToUser($emails, $mailTitle, $mailContent)
  {
    $stmpSetting = Bl_Config::get('stmp');
    static $mailInstance;
    if (!isset($mailInstance)) {
      $mailInstance = new Mail_Model($stmpSetting);
    }
    $status = 0;
    foreach ($emails as $email) {
      if(!$mailInstance->sendMail($email, $mailTitle, $mailContent, 'html')) {
        $status++;
      }
    }
    if ($status == 0) {
      setMessage(t('Success, please check your mail'));
    } else {
      setMessage($status . t('email sent not succeed'), 'error');
    }
  }

//
/**************************************************************************/
//DB

  /**
   * 获得所有用户名称和邮件列表
   *
   */
  private function getUsersEmailList()
  {
    global $db;
    $list = array();
    $cacheId = 'mail-subscribe-list';
    if ($cache = cache::get($cacheId)) {
      $list = $cache->data;
    } else {
      $result = $db->query('SELECT * FROM `widget_mailsubscribe` WHERE status="1" ORDER BY timestamp DESC');
      $list = $result->all();
      if (is_array($list) && count($list)>0) {
        cache::save($cacheId, $list);
      }
    }
    return $list;
  }

  /**
   * 保存邮件地址
   * @param String $email
   */
  private function saveEmailAddressToDB($email)
  {
    global $db;
    $tableName = 'widget_mailsubscribe';
    $set = array(
      'email' => $email,
      'key' => randomString(20),
      'status' => 1,
      'timestamp' => TIMESTAMP,
    );
    $cacheId = 'mail-subscribe-list';
    cache::remove($cacheId);
    $db->insert($tableName, $set);
  }

  /**
   * 检查邮件地址是否存在
   * @param String $email
   */
  private function checkIfEmailExist($email)
  {
    global $db;
    $sql = 'SELECT `key` FROM widget_mailsubscribe WHERE email="'.$db->escape($email).'" ';
    $result = $db->query($sql);
    if ($result->row()) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * 数据库，取消邮件订阅
   * @param String $key
   */
  private function cancelSubscribeDB($key)
  {
    global $db;
    $sql = 'SELECT `email`,`key`,`status` FROM widget_mailsubscribe WHERE `key`="'.$db->escape($key).'" ';
    $result = $db->query($sql);
    if ($list = $result->row()) {
      if ($list->status==1) {
        $db->update('widget_mailsubscribe', array('status' => 0), array('key' => $key));
        return array(0, $list->email);
      }
      return array(1, $list->email);
    } else {
      return array(2, '');
    }
  }

  /**
   * 数据库，取消邮件订阅
   * @param String $email
   */
  private function cancelSubscribeDBByEmail($email)
  {
    global $db;
    $sql = 'SELECT `email`,`key`,`status` FROM widget_mailsubscribe WHERE `email`="'.$db->escape($email).'" ';
    $result = $db->query($sql);
    if ($list = $result->row()) {
      if ($list->status==1) {
        //$db->update('widget_mailsubscribe', array('status' => 0), array('email' => $email));
        $db->query('DELETE FROM widget_mailsubscribe WHERE `email`="'.$db->escape($email).'"');
        return array(0, $list->key);
      }
      return array(1, $list->key);
    } else {
      return array(2, '');
    }
  }

  private function createMailsubscribeTable()
  {
    global $db;
    $sql = 'CREATE  TABLE IF NOT EXISTS `widget_mailsubscribe` (
              `email` VARCHAR(128) NOT NULL ,
              `key` CHAR(32) NOT NULL DEFAULT \'\' ,
              `status` TINYINT(1) NOT NULL DEFAULT 1 ,
              `timestamp` INT UNSIGNED NOT NULL DEFAULT 0 ,
              PRIMARY KEY (`email`) )
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci';
    $db->exec($sql);
  }

}