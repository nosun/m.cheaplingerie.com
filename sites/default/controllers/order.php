<?php
class Order_Controller extends Bl_Controller
{
  private $_orderInstance;

  public function init()
  {
    $this->_orderInstance = Order_Model::getInstance();
  }

  public function firstlistAction($page = 1, $status = null){
    unset($_SESSION['listorderf']);
    gotourl('order/list/' . $page . '/' . $status);
  }
  
  public function listAction($page = 1, $status = null)
  {
  	global $user;
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    $pageRows = 5;

    if ($this->isPost()) {
      unset($_SESSION['listorderf']);
      $filter = $_POST;
      foreach ($_POST as $key=>$dl) {
	      $_SESSION['listorderf'][$key] = $dl;
	    }
	  }
    if(isset($_SESSION['listorderf'])){
      $filter = $_SESSION['listorderf'];
    }

    $paymentInstance = Payment_Model::getInstance();
    $paymentList = $paymentInstance->getPaymentsList();

    $this->view->assign('number', isset($filter['number']) ? $filter['number'] : null);
    $this->view->assign('startTime', isset($filter['startTime']) ? $filter['startTime'] : null);
    $this->view->assign('endTime', isset($filter['endTime']) ? $filter['endTime'] : null);
    $this->view->assign('status', isset($filter['status']) ? $filter['status'] : null);
    $filter['uid'] = $user->uid;
    $filter['status'] = isset($filter['status']) ? $filter['status'] : $status;
    $ordersList = $this->_orderInstance->getOrdersList($filter, $page, $pageRows);
  	foreach ($ordersList as $k => $v) {
  	  $ordersItems =  $this->_orderInstance->getOrderItems($v->oid);
  	  $ordersItems = array_splice($ordersItems, 0, 1);
  	  $ordersList[$k]->firstitem = isset($ordersItems[0]) ? $ordersItems[0] : null;
  	  $ordersList[$k]->payment = $paymentList[$v->payment_method];
  	}
  
    $orderscount = $this->_orderInstance->getOrdersCount($filter);
    $this->view->assign('tmark', 'order');
    $this->view->assign('templatefile', 'u_orderlist.phtml');
    $this->view->addCss(url('styles/themes/base/jquery-ui-1.8.19.custom.css'));
    if ($page == 1) {
      $_SESSION['FirstPath']['orderList'] = trim($_SERVER['REQUEST_URI'], '/');
    }
    $firstPath = isset($_SESSION['FirstPath']['orderList']) ? $_SESSION['FirstPath']['orderList'] : null;
    
    $this->view->render('personalcenter.phtml', array(
      'status' => $status,
      'ordersList' => $ordersList,
      'paymentList' => $paymentList,
      'pagination' => callFunction('common_pagination', 'order/list/%d/' . $status, ceil($orderscount/$pageRows), $page, $firstPath),
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
  
  public function infoAction($oid)
  {
  	global $user;
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    $orderInfo = $this->_orderInstance->getOrderInfo($oid);
    if (!$orderInfo) {
      goto404(t('error'));
    }
    if($orderInfo->uid == $user->uid){
	    $orderInfo->user = $user;
	    $shippingList = Bl_Plugin::getList('shipping');
	    $orderInfo->goods_amount = 0;
	    $orderInfo->goods_number = 0;
	    $orderInfo->goods_weight = 0;
	    if (isset($orderInfo->items)) {
	      foreach ($orderInfo->items as $k => $v) {
	        $orderInfo->goods_amount += $v->sell_price * $v->qty;
	        $orderInfo->goods_number += $v->qty;
	        $orderInfo->goods_weight += $v->wt;
	       }
	    }
	    $siteInstance = Site_Model::getInstance();
	    $currencyList = $siteInstance->getCurrenciesList($visible = null);
	    $shippingInstance = Shipping_Model::getInstance();
	    $shippingList = $shippingInstance->shippingList(true);
	    $payment = $orderInfo->payment_method;
	    $paymentList = $this->getPaymentList($orderInfo);
	    $orderInfo->payment = $paymentList[$payment];

	    $this->view->assign('tmark', 'order');
	    $this->view->render('user_myorderview.phtml', array(
	      'orderInfo' => isset($orderInfo) ? $orderInfo : null,
	      'currencyList' => isset($currencyList) ? $currencyList : null,
	      'shippingList' => isset($shippingList) ? $shippingList : null,
	      'paymentList' => isset($paymentList) ? $paymentList : null,
	      'submitform' => isset($submitform) ? $submitform : null,
	    ));
    }else{
    	gotoUrl('');
    }
}

  public function removeAction($oid){
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
  	if (!$orderInfo = $this->_orderInstance->getOrderInfo($oid)) {
  		goto404(t('error'));
  	}
    if ($this->_orderInstance->updateStatusOrder($oid, array('status' => Order_Model::ORDER_CANCEL))) {
      $emailSetting = Bl_Config::get('orderCancelEmail');
      if (isset($emailSetting) && $emailSetting['status']) {
        $stmpSetting = Bl_Config::get('stmp');
        if (isset($stmpSetting) && $stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
          $mailInstance = new Mail_Model($stmpSetting);
          $email[] = isset($orderInfo->delivery_email) ? $orderInfo->delivery_email : null;
          $siteInfo = Bl_Config::get('siteInfo', array());
          
          if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
          	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          }
           $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
          $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
          if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
//             setMessage('Success, please check your mail');
          } else {
//             setMessage('send mail error', 'error');
          }
        } else {
//           setMessage('Mail server information is not configured properly, please check', 'error');
        }
      }
      
      	setMessage(t('Remove successful'));
	  	$status = callFunction('order', 'before_cancel', $_POST);
	    if (!isset($status) || $status) {
	    	if (Bl_Config::get('stockCheck', false)) {
          $productInstance = Product_Model::getInstance();
          foreach ($orderInfo->items as $item) {
            $productInstance->updateProductStock($item->pid, - $item->qty);
          }
        }
	      callFunction('order', 'after_cancel', array('oid' => $oid));
	    }
    }
    gotoUrl('user/myorders');
  }
}