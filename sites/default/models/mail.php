<?php
class Mail_Model extends Bl_Model
{
	public $_mail;

	private $_var;

  /**
   * @return Mail_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  public function __construct($set)
  {
  	require LIBPATH.'/class.phpmailer.php';
	  $this->_mail = new PHPMailer();
	  $this->_mail->IsSMTP();
		$this->_mail->CharSet = "utf-8";
		$this->_mail->SMTPDebug = 0;
		$this->_mail->SMTPAuth = true;
		$this->_mail->SMTPSecure = "ssl";
		$this->_mail->Host = $set['stmpserver'];
		$this->_mail->Port = $set['stmpport'];
		$this->_mail->Username = $set['stmpuser'];
		$this->_mail->Password = $set['stmppasswd'];
//		$this->_mail->SMTPDebug = true;

		if(!isset($this->_var)){
			$this->_var = new stdClass();
		}
		
		$this->_var->mailfrom = isset($set['mailfrom']) ? $set['mailfrom'] : $set['stmpuser'];
		$this->_var->mailfromname = $set['mailfromname'] ? $set['mailfromname'] : $set['stmpuser'];
		$this->_var->mailreply = $set['mailreply'] ? $set['mailreply'] : $set['stmpuser'];
		$this->_var->mailreplyname = $set['mailreplyname'] ? $set['mailreplyname'] : $set['stmpuser'];
	}

	/**
	 *
	 * 发送邮件方法
	 * @param string $address 发送邮件地址，多个用,隔开
	 * @param string $subject 邮件标题
	 * @param string $content 邮件内容
	 */
  public function sendMail($address, $subject, $content, $ishtml , $username = '')
  {
    $this->_mail->SetFrom($this->_var->mailfrom, $this->_var->mailfromname);
    $this->_mail->AddReplyTo($this->_var->mailreply, $this->_var->mailreplyname);
    $this->_mail->Subject = $subject;
    if ($ishtml == 'html') {
    	$this->_mail->MsgHTML($content);
    } else {
    	$this->_mail->Body = $content;
    }
    $this->_mail->ClearAddresses();
    $this->_mail->ClearBCCs();
    if (is_array($address)) {
      if (isset($address[0]))
      $this->_mail->AddAddress($address[0], $username);
      if (isset($address[1]))
      $this->_mail->AddBCC($address[1], $username);
    } else {
      $this->_mail->AddAddress($address, $username);
    }
    if(!$this->_mail->Send()) {
      return false;
    } else {
      return true;
    }
  }

  public function ReplaceMailToken($content, $orderInfo = null, $uid = null)
  {
     global $user;
     if(!isset($uid)){
     	$uid = $user->uid;
     }
     $userInstance = User_Model::getInstance();
     $userInfo = $userInstance->getUserInfo($uid);
     //the $userInfo is not always corrected. Maybe the admin changed the status, and sent the mail.
     //So add a workaround for the correct $userInfo;
     
     if(isset($orderInfo)){
        $userInfo = User_Model::getInstance()->getUserInfo($orderInfo->uid);
     }

     $siteInfo = Bl_Config::get('siteInfo', array());
     $siteInfo['siteurl'] = "http://m.shirleysdress.com/";
     $contactWay = Bl_Config::get('contactWay', array());
     
     $usertokens = array('name', 'email');
     $sitetokens = array('sitename', 'siteurl', 'email', 'copyright', 'template');
     $ordertokens = array('oid', 'number','delivery_first_name','delivery_last_name','delivery_email',
       'delivery_phone','delivery_mobile','delivery_country','delivery_province','delivery_city',
       'delivery_address','delivery_postcode','delivery_time','payment_method','payment_status',
       'shipping_method','shipping_no','shipping_fee','paySubmit','goods_number','estimated_delivery_date',
     );

     $content = str_replace('{[time]}', date('Y-m-d H:i:s', TIMESTAMP), $content);

     foreach ($usertokens as $v) {
       $content = str_replace('{[user.' . $v . ']}', $userInfo->$v, $content);
     }

     foreach ($sitetokens as $v) {
       if($v =='siteurl'){
        $siteurl = str_replace('http://', '', $siteInfo[$v]);
        $siteurl = trim($siteurl, '/');

        $siteInfo[$v] = 'http://' . $siteurl . '/';
       }else if($v == 'template'){
       	$siteInfo['template'] = Bl_Config::get('template', 'default');
       }
       
       $content = str_replace('{[site.' . $v . ']}', $siteInfo[$v], $content);
     }

     foreach ($ordertokens as $v) {
       if(isset($orderInfo)){
         if($v == 'shipping_method'){
           if(strtoupper($orderInfo->$v) == 'EMS'){
             $content = str_replace('{[order.' . $v . ']}', 'Standard Shipping', $content);    
           }else if(strtoupper($orderInfo->$v) == 'UPS'){
             $content = str_replace('{[order.' . $v . ']}', 'Expedited Shipping', $content);    
           }else{
             $content = str_replace('{[order.' . $v . ']}', $orderInfo->$v, $content);
           }
         }else if($v == 'shipping_fee' && isset($orderInfo->fees['shipping']->fee_value)){
           $content = str_replace('{[order.' . $v . ']}', c($orderInfo->fees['shipping']->fee_value), $content);
         }else if($v == 'payment_status' && isset($orderInfo->status_payment)){
           $status_str = 'Not Paid';
           if($orderInfo->status_payment == 1){
             $status_str = 'Paid';
           }
           $content = str_replace('{[order.' . $v . ']}', $status_str, $content);
         }else if($v == 'shipping_no' && isset($orderInfo->data['shipping_no'])){
         	$content = str_replace('{[order.' . $v . ']}', $orderInfo->data['shipping_no'], $content);
         }
         else if(property_exists($orderInfo, $v)){
         	$content = str_replace('{[order.' . $v . ']}', $orderInfo->$v, $content);
         }
       }
     }

       if (isset($orderInfo) && isset($orderInfo->items)) {
       	/*First echo output buffer*/
       	ob_start();
       	/*Then render the email template for out put*/
       	$view = new Bl_View();
       	$view->assign('orderInfo', $orderInfo);
       	
       	$view->render('mail/order_detail.phtml');
       	$str = ob_get_clean();
       	/*Then continue the output buffer*/
       	$content = str_replace('{[order.items]}', $str, $content);
     }

     return $content;
  }

}
