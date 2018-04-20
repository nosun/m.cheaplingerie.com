<?php
class Admin_Order_Controller extends Bl_Controller
{
  private $_orderModel;

  public static function __permissions()
  {
    return array(
      'list order',
      'edit order',
      'delete order',
    	'order setting',
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_orderModel = Order_Model::getInstance();
  }

  public function firstListAction($key)
  {
    if ($key == 'all') {
      foreach ($_SESSION['listorder'] as $key1 => $dl) {
        unset($_SESSION['listorder'][$key1]);
      }
    } else {
      unset($_SESSION['listorder'][$key]);
    }
    gotourl('admin/order/getlist');
  }

  public function getListAction($page = 1)
  {
    if (!access('list order')) {
      goto403('Do not have access');
    }
    $pageRows = 20;
    $filter = array();
    if ($this->isPost()) {
      $filter = $_POST;
      foreach ($_POST as $key=>$dl) {
        $_SESSION['listorder'][$key] = $dl;
      }
    }
    if(isset($_SESSION['listorder'])){
        $filter = $_SESSION['listorder'];
    }
    $ordersList = $this->_orderModel->getOrdersList($filter, $page, $pageRows);
    $orderscount = $this->_orderModel->getOrdersCount($filter);
    $selectHtml = $this->_orderModel->getSelectHtml($filter);
    $this->view->addCss(url('styles/themes/base/jquery.ui.datepicker.css'));
    $this->view->render('admin/order/orderslist.phtml', array(
     'ordersList' => $ordersList,
     'selectHtml' => $selectHtml,
     'pagination' => pagination('admin/order/getlist/%d', $orderscount, $pageRows, $page),
    ));
  }

  public function getInfoAction($oid)
  {
    if (!access('list order')) {
      goto403('Do not have access');
    }
    $orderInfo = $this->_orderModel->getOrderInfo($oid);
    if (!$orderInfo) {
      setMessage('This order can not found!');
      gotoUrl('admin/order/getList');
    }
    $userModel = User_Model::getInstance();
    $orderInfo->user = $userModel->getUserInfo($orderInfo->uid);
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
    $paymentList = Bl_Plugin::getList('payment');
    $orderInfo->payment_name = $paymentList[$orderInfo->payment_method]->name;
    $siteInstance = Site_Model::getInstance();
    $countries = $siteInstance->getCountries();
    $cid = $orderInfo->delivery_cid ? $orderInfo->delivery_cid : key($countries);
    $provinces = $siteInstance->getProvinces($cid);
    $this->view->render('admin/order/orderinfo.phtml', array(
      'orderInfo' => $orderInfo,
      'shippingList' => isset($shippingList) ? $shippingList : null,
      'countries' => $countries,
      'provinces' => $provinces
    ));
  }

  public function updateOrderAction()
  {
    global $user;
    if (!access('edit order')) {
      goto403('Do not have access');
    }
    if ($this->isPost()) {
      if(isset($_POST['addOrderItem'])){
        $this->insertOrderItem($_POST);
        setMessage('添加商品成功', 'notice');
      } else {
        if (!$orderInfo = $this->_orderModel->getOrderInfo($_POST['oid'])) {
          setMessage('This order can not found!');
          gotoUrl('admin/order/getList');
        }
        if (isset($_POST['shipping_no'])) {
          $_POST['data']['shipping_no'] = $_POST['shipping_no'];
        }
        $post = $_POST;
        if ($_POST['old_status_payment'] == '0' && $_POST['status_payment'] == '1') {
        	$post['payment_time'] = TIMESTAMP;
        }
        $this->_orderModel->updateOrder($_POST['oid'], $post);
        //After update, get the order again for the latest information.
        $orderInfo = $this->_orderModel->getOrderInfo($_POST['oid']);
        
        
        if ($_POST['old_status_shipping'] == '0' && $_POST['status_shipping'] == '1') {
          $emailSetting = Bl_Config::get('deliverGoodsEmail', array(0));
          if ($emailSetting['status']) {
            $stmpSetting = Bl_Config::get('stmp', 0);
            if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
            	$mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
              $email[] = $_POST['delivery_email'];
              $siteInfo = Bl_Config::get('siteInfo', array());
              if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
              	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          	  }
          	  $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
              
              $customerInfo = User_Model::getInstance()->getUserInfo($orderInfo->uid);
              $customerName = isset($customerInfo->name)?$customerInfo->name : $customerInfo->email;
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'], $customerName)) {
                setMessage('Shipping email Success, please check your mail');
              } else {
                setMessage('send mail error', 'error');
              }
            } else {
              setMessage('Mail server information is not configured properly, please check', 'error');
            }
          }
        }
        
        if($_POST['old_shipping_no'] != $_POST['shipping_no'] && $_POST['shipping_no'] != ''){
        	//send email for shipping number.
          $emailSetting = Bl_Config::get('orderShippingNoEmail', array(0));
          if ($emailSetting['status']) {
            $stmpSetting = Bl_Config::get('stmp', 0);
            if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
            	$mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
              $email[] = $_POST['delivery_email'];
              $siteInfo = Bl_Config::get('siteInfo', array());
              if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
              	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          	  }
          	  $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
              
              $customerInfo = User_Model::getInstance()->getUserInfo($orderInfo->uid);
              $customerName = isset($customerInfo->name)?$customerInfo->name : $customerInfo->email;
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'], $customerName)) {
                setMessage('Successfully sent the shipping number email, please check.');
              } else {
                setMessage('send mail error', 'error');
              }
            } else {
              setMessage('Mail server information is not configured properly, please check', 'error');
            }
          }
        }

        if ($_POST['old_status_payment'] == '0' && $_POST['status_payment'] == '1') {
          $emailSetting = Bl_Config::get('orderPayEmail', array(0));
          if ($emailSetting['status']) {
            $stmpSetting = Bl_Config::get('stmp', 0);
            if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
              $mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
              $email[] = $_POST['delivery_email'];
              $siteInfo = Bl_Config::get('siteInfo', array());
              if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
              	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          	  }
          	  $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
                setMessage('Pay Success, please check your mail');
              } else {
                setMessage('send mail error', 'error');
              }
            } else {
              setMessage('Mail server information is not configured properly, please check', 'error');
            }
          }
        }
        setMessage('修改订单信息成功', 'notice');
        gotourl('admin/order/getinfo/'.$_POST['oid']);
      }
    } else {
      goto404('访问错误');
    }
  }

  public function deleteAction($oid, $delWay = false){
    if (!access('delete order')) {
      goto403('Do not have access');
    }
    if (!$delWay) {
      //软删除
      if (!$this->_orderModel->getOrderInfo($oid)) {
        setMessage('This order can not found!');
        gotoUrl('admin/order/getlist');
      }
      $post = array('status' => Order_Model::ORDER_DEL);
      $this->_orderModel->updateStatusOrder($oid, $post);
      setMessage('删除订单成功', 'notice');
    } else {
      //硬删除
    }
    gotourl('admin/order/getlist');
  }

  public function deleteOrderItemAction($oid, $oiid, $delWay = false)
  {
    if (!access('edit order')) {
      goto403('Do not have access');
    }
    if (!$delWay) {
      //软删除
      if (!$this->_orderModel->updateStatusOrderItem($oid, $oiid)) {
        setMessage('删除订单商品出错', 'error');
      } else {
        setMessage('删除订单商品成功', 'notice');
      }

    } else {
      //硬删除
    }
    gotourl('admin/order/getinfo/'.$oid);
  }

  private function insertOrderItem($post){
    if (!access('edit order')) {
      goto403('Do not have access');
    }
     $productModel = Product_Model::getInstance();
     $productInfo = $productModel->getProductInfoBySn($post['new_pid']);
     if ((boolean)$productInfo) {
       $this->_orderModel->insertOrderItem($post['oid'], $productInfo);
     } else {
       setMessage('商品不存在', 'error');
     }
     gotourl('admin/order/getinfo/'.$post['oid']);
  }

  public function printAction($type, $oid)
  {
    if (!access('list order')) {
      goto403('Do not have access');
    }
    $orderInfo = $this->_orderModel->getOrderInfo($oid);
    $userModel = User_Model::getInstance();
    $orderInfo->user = $userModel->getUserInfo($orderInfo->uid);
    $this->view->render('admin/order/printf2.phtml', array(
      'orderInfo' => $orderInfo,
    ));
  }

  public function mailsettingAction()
  {
    if (!access('setting')) {
      goto403('Do not have access');
    }
    if ($this->isPost()) {
      $orderTradingEmail = isset($_POST['orderTradingEmail']) ? $_POST['orderTradingEmail'] : "";
      $orderCancelEmail = isset($_POST['orderCancelEmail']) ? $_POST['orderCancelEmail'] : "";
      $deliverGoodsEmail = isset($_POST['deliverGoodsEmail']) ? $_POST['deliverGoodsEmail'] : "";
      $orderShippingNoEmail = isset($_POST['orderShippingNoEmail']) ? $_POST['orderShippingNoEmail']: "";
      
      Bl_Config::set('orderTradingEmail',$orderTradingEmail);
      Bl_Config::set('orderCancelEmail',$orderCancelEmail);
      Bl_Config::set('deliverGoodsEmail',$deliverGoodsEmail);
      Bl_Config::set('orderShippingNoEmail', $orderShippingNoEmail);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/order/mailsetting');
    } else {
      $orderTradingEmail = Bl_Config::get('orderTradingEmail', 0);
      $orderCancelEmail = Bl_Config::get('orderCancelEmail', 0);
      $deliverGoodsEmail = Bl_Config::get('deliverGoodsEmail', 0);
      $orderShippingNoEmail = Bl_Config::get('orderShippingNoEmail', 0);
      $this->view->render('admin/order/mailsetting.phtml', array(
        'orderTradingEmail' => $orderTradingEmail,
        'orderCancelEmail' => $orderCancelEmail,
        'deliverGoodsEmail' => $deliverGoodsEmail,
        'orderShippingNoEmail' => $orderShippingNoEmail,
      ));
    }
  }
  
  public function settingAction()
  {
    if (!access('order setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
    	$_POST['order_prefix'] = preg_replace('/[^0-9a-zA-Z]+/', '', $_POST['order_prefix']);
      $prefix = isset($_POST['order_prefix']) ? $_POST['order_prefix'] : "";
      Bl_Config::set('order.prefix', strtolower(substr($prefix, 0, 4)));
      Bl_Config::save();
      setMessage('设置成功');
      gotoUrl('admin/order/setting');
    } else {
      $prefix = Bl_Config::get('order.prefix');
      $this->view->render('admin/order/setting.phtml', array(
        'prefix' => $prefix,
      ));
    }
  }
}