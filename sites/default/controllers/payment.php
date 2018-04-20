<?php
class Payment_Controller extends Bl_Controller
{
  public static function __router($paths)
  {
    $paymentInstance = Payment_Model::getInstance();
    if (!isset($paths[1]) || !($instance = $paymentInstance->getPaymentInstance($paths[1]))) {
      goto404(t('Payment not found'));
    }
    $action = array_shift($paths);
    $paths[0] = $instance;
    return array(
      'action' => $action,
      'arguments' => $paths,
    );
  }

  /**
   * @param Payment_Server_Interface $instance
   */
  public function noticeAction($instance)
  {
    if ($instance instanceof Payment_Server_Interface) {
      $return = $instance->callback();
      if (isset($return) && $return['verified']) {
      	$orderInstance = Order_Model::getInstance();
      	$orderInfo = $orderInstance->getOrederInfoByNumber($return['orderNumber']);
        if ($return['updateOrderStatus'] && true === $orderInstance->dealOrderStatus($return)) {
          widgetCallFunctionAll('pay', $return);
	        callFunction('order', 'after_pay', array('oid' => $orderInfo->oid));
        }
        $this->sendPaymentEmail($orderInfo);
      }
    } else {
      // TODO 通知接口无效
    }
  }
  
  /**
   * @param Payment_Interface $instance
   */
  public function callbackAction($instance, $orderNumber=null)
  {
    if ($instance instanceof Payment_Interface) {
      $return = $instance->callback($orderNumber);
      if ($return === false) {
      	gotoBack('user/myorders/');
      }
      $orderInstance = Order_Model::getInstance();
      $number = $return['orderNumber'];
      $orderInfo = $orderInstance->getOrederInfoByNumber($number);
      $paymentList = $this->getPaymentList($orderInfo);
      if ($orderInfo->status_payment != 1) {
	      if ($return['updateOrderStatus'] && true === $orderInstance->dealOrderStatus($return)) {
	        $orderInfo->status_payment = 1;
	      }
	      if (isset($return) && $return['verified']) {
	        $productInstance = Product_Model::getInstance();
	        if ($orderInfo->items) {
	  	      foreach ($orderInfo->items as $k => $v) {
	  	        $orderInfo->goods_number += $v->qty;
	  	        $productInstance->updateTransactions($v->pid, $v->qty);
	  	      }
	        }
	        if (isset($return['redirect']) && !empty($return['redirect'])) {
	          gotoUrl($return['redirect']);
	        }
	        if (isset($return['send_email']) && $return['send_email'] === true) {
	        	$this->sendPaymentEmail($orderInfo);
	        }
	        $orderInfo->payment = $paymentList[$orderInfo->payment_method];
	        $shippingInstance = Shipping_Model::getInstance();
	        $shippingList = $shippingInstance->shippingList(true);
	        $orderInfo->shipping = $shippingList[$orderInfo->shipping_method];
	        $_SESSION['messages'] = null;
	        setMessage(isset($return['message']) ? $return['message'] : '', 'info');
	      } else {
	      	$_SESSION['messages'] = null;
	      	setMessage($return["message"], 'error');
	      }
	    } else {
	    	$_SESSION['messages'] = null;
	        setMessage(t('Payment does not exist'), 'error');
	    }
    }

  	$frontInstance = Front_Model::getInstance();
  	$recommendProductList = $frontInstance->getProductsListBySpecial(array('special_tid' =>30), 1, 12);
  	
  	if($orderInfo->status_payment == 1){
  		$this->view->setTitle('Payment Success');
  	}else{
  		$this->view->setTitle('Payment Failure');
  	}
  	
    $this->view->render('checkoutcallback.phtml', array(
      'orderInfo' => isset($orderInfo) ? $orderInfo : null,
      'verified' => isset($return['verified']) ? $return['verified'] : false,
      'paymentList' => isset($paymentList) ? $paymentList : null,
      'recommandProductList' => $recommendProductList,
    ));
  }

  private function getPaymentList($orderInfo) {
  	$paymentInstance = Payment_Model::getInstance();
  	$paymentList = $paymentInstance->getPaymentsList();
  
  	foreach ($paymentList as &$paymentInfo) {
  		$payment = strtolower(preg_replace('/\s+/', '', $paymentInfo->name));
  		if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && $paymentInfo->status && $paymentInfo->visible) {
  			$info = $paymentInstance->getOrderPaymentInfo($orderInfo);
  			$submitform = $instance->getSubmitForm($info);
  			$paymentInfo->submitform = $submitform;
  		}
  	}
  	return $paymentList;
  }
  private function sendPaymentEmail($orderInfo) {
  	$emailSetting = Bl_Config::get('orderPayEmail', array(0));
  	if ($emailSetting['status']) {
  		$stmpSetting = Bl_Config::get('stmp', 0);
  		if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
  			$mailInstance = new Mail_Model($stmpSetting);
  			$email[] = $orderInfo->delivery_email;
  			$siteInfo = Bl_Config::get('siteInfo', array());
  			if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
  				$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
  			}
  			//added by pzzhang: FIX BUG: Can Not Render the Payment Notice Email Parameters.
  			$emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
  			$emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
  			//end added by pzzhang.
  			if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
  			} else {
  				log::save('error', t('send mail error'), $orderInfo);
  			}
  		} else {
  			log::save('error', t('Mail server information is not configured properly, please check'), $stmpSetting);
  		}
  	}
  }
  
  public function failAction($instance)
  {
    echo '<h1>ERROR</h1>';
    var_dump($_GET, $_POST);
  }
}
