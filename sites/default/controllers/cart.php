<?php
class Cart_Controller extends Bl_Controller
{
  private $_cartInstance;

  public function init()
  {
    $this->_cartInstance = Cart_Model::getInstance();
  }

  public function indexAction()
  {
    gotoUrl('cart/getcarinfo');
  }

  public function getCarInfoAction()
  {
  	
  	$cartInfo = $this->_cartInstance->getCartProductList();
    $cartInfo_new = callFunction('cart', 'list', $cartInfo);
    if (isset($cartInfo_new)) {
    	$cartInfo = $cartInfo_new;
    }
    
    $frontInstance = Front_Model::getInstance();
 //   $recommendProductList = $frontInstance->getProductsListBySpecial(array('termname' =>'Recommended'));
    $recommendProductList = $frontInstance->getProductsListBySpecial(array('special_tid' =>30), 1, 9);
    
    $this->view->setTitle('Cart Information');
    
    $this->view->render('cart.phtml', array(
      'cartInfo' => isset($cartInfo) ? $cartInfo : null,
      'recommandProductList' => $recommendProductList,
    ));
  }

  public function ajaxgetprovinceAction($cid)
  {
    $siteInstance = Site_Model::getInstance();
    $provinces = $siteInstance->getProvinces($cid);
    echo json_encode($provinces);
  }

  public function ajaxGetCartAction(){
    $productInstance = Product_Model::getInstance();
    $cartInfo = $this->_cartInstance->getCartProductList(null, null, null, false);
    $cartProductList = isset($cartInfo->product) ? $cartInfo->product : array();
    $newarr = array();
    if ($cartProductList) {
      $total = 0;
      foreach ($cartProductList as $k => $v) {
        $array[$k] = array(
          'name' => $v->name,
          'quantity' => $v->qty,
          'imgfilepath' => $v->filepath,
          'price' => $v->price,
          'list_price' => $v->list_price,
          'subtotal' => $v->price * $v->qty,
          'cart_item_id' => $v->cart_item_id,
        );
      }
      $newarr = array(
        'total' => isset($cartInfo->goods_number) ? $cartInfo->goods_number : 0,
        'amount' => $cartInfo->goods_amount,
        'saving_amount' => $cartInfo->total_save_amount,
      	'weight' => isset($cartInfo->goods_weight) ? $cartInfo->goods_weight : 0,
        'product' => $array
      );
    }
    echo json_encode($newarr);
  }

  // 提交订单的处理函数
  public function productpostAction()
  {
  	if ($this->isPost()) {
  		$post = $_POST;
	  	if (isset($post['checkout'])) {
	  		gotourl('cart/checkout/'.$post['pid'].'/0/'.$post['qty']);
	  	} elseif (isset($post['cart'])) {
	  	  $data = isset($post['data']) ? $post['data'] : null;
	  	  /* 参数校验*/
	  	  $productInstance = Product_Model::getInstance();
	  	  $product = $productInstance->getProductInfo($post['pid']);
	  	  $type = $product->type;
	  	  if ($productInstance->checkTypeExist($type)) {
	  	  	$product->fields = $productInstance->getTypeFieldsList($type);
	  	  } else {
	  	  	$product->fields = array();
	  	  }
	  	  foreach ($product->fields as $fieldName => $field) {
	  	  	if(!empty($product->{'field_' . $fieldName}) && !$field->is_spec && $field->required && empty($data[$fieldName]) && empty($data[ucfirst($fieldName)])) {
	  	  		$reffer_url = $_SERVER["HTTP_REFERER"];
	  	  		echo "<script>alert('Please select your ". $fieldName . "');window.location.replace('" . $reffer_url . "');</script>";
	  	  		exit;
	  	  	}
	  	  }
		  if(isset($data['Size'])){
	  	  	if ($data['Size'] != "custom")
	  	  	{
	  	  		unset($data["Bust (Inch)"]);
	  	  		unset($data["Waist (Inch)"]);
	  	  		unset($data["Hips (Inch)"]);
	  	  		unset($data["Hollow to Floor (Inch)"]);
	  	  		unset($data["Height (Inch)"]);
	  	  	} else {
	  	  		if (isset($data["Size"])) {
	  	  			unset($data["Size"]);
	  	  		}
	  	  	}
	  	  }
	  	  $this->addtoCartAction($post['pid'], $post['qty'], $data, false);
	  	} else{
	  		$reffer_url = $_SERVER["HTTP_REFERER"];
            header("Location: ".$reffer_url);
            exit;
	  	}
  	} else {
      goto404(t('Access Denied'));
    }
  }
  
  public function ajaxAddProductAction()
  {
  	if ($this->isPost()) {
	  	$post = $_POST;
	  	if (isset($post['cart'])) {
	  	  $data = isset($post['data']) ? $post['data'] : null;
	  	  $this->addtoCartAction($post['pid'], $post['qty'], $data, true);
	  	} else{
	  		echo json_encode(array('success'=>false, 'message'=>'No valid information received.'));
	  	}
  	} else {
      echo json_encode(array('success'=>false, 'message'=>'No valid information received.'));
    }
  }

  public function cartpostAction()
  {
    if ($this->isPost()) {
	    $post = $_POST;
	    if (isset($post['delete'])) {
	      $cart_item_ids = implode(",",$post['cart_item_id_checked']);
	      gotourl('cart/deleteProductFromCart/'.$cart_item_ids);
	    } elseif (isset($post['checkout'])) {
	    	if (!isset($post['cart_item_id_checked'])) {
	    		gotoUrl('cart/getcarinfo');
	    	}
	    	$pids = implode(",", $post['cart_item_id_checked']);
	    	// added by chenzhigao for cart item comment
	    	foreach ($post['cart_item_requirement'] as $cart_item_id => $comment) {
	    	    if ($comment != "If you have any other requirements for this item, please feel free to leave a message here.") {
	    	        if (Cart_Model::getInstance()->addCartProductComment($cart_item_id, $comment) <= 0) {
	    	            log::save('debug', 'add cart product comment failed', array($cart_item_id, $comment));
	    	        }
	    	    }
	    	}
	      gotourl('cart/checkout/'.$pids);
	    } elseif (isset($_POST['save']) || isset($_POST['save_x']) || isset($_POST['save_y'])) {
	      if ($post['cart_item_ids']) {
	        foreach ($post['cart_item_ids'] as $k => $v) {
	          $set = array(
	            'qty' => $post['qtys'][$k],
	          );
	          $this->_cartInstance->updateCartProduct($v, $set);
	        }
	      } else {
	        setMessage(t('No item was selected to be added. '));
	      }
	      gotourl('cart');
	    }  else{
        $reffer_url = $_SERVER["HTTP_REFERER"];
        header("Location: ".$reffer_url);
      }
 	  } else {
      goto404(t('Access Denied'));
    }
  }

  public function addtoCartAction($pid, $qty, $data = null, $isAjax = false)
  {
  	$status = callFunction('cart', 'before_add', array('pid' => $pid, 'qty' => $qty));
  	
  	$productInstance = Product_Model::getInstance();
  	$errorMessage = '';
  	if (!isset($status) || $status) {
  		$productinfo = $productInstance->getProductInfo($pid);
  		if (!$productinfo->status) {
  		  if($isAjax){
  		     echo json_encode(array('success'=>false, 'message'=>'Sorry, this item was off shelves.'));
  		     return;
  		  }else{
  			setMessage('Sorry, this item was off shelves.', 'error');
  			$reffer_url = $_SERVER["HTTP_REFERER"];
    		header("Location: ".$reffer_url);
    		exit;
  		  }
  		}

  		$cartInfo = $this->_cartInstance->getCartProductInfoByPid($pid, $data);

	  	if (!$cartInfo) {

	      $set = array(
	        'pid' => $pid,
	        'qty' => $qty,
	        'data' => $data,
	      );
	      $message = $productInstance->checkQtyOfCommodiy_($pid, $set['qty']);
		  if ($message) {
		        $errorMessage = $message;  
	      } else {
	          if ($this->_cartInstance->insertProductToCart($set)) {
	          	log::save('debug', 'session', $_SESSION);
	          	
	          	if(!isset($_SESSION['goodsInCart'])){
	          		$_SESSION['goodsInCart'] = 1;
	          	}else{
	          		$_SESSION['goodsInCart']++;
	          	}
	          	//stop coupon
	          	//widgetCallFunction('coupon', 'hook_order', array('op' => 'after'));
	          }
	        }
	    } else {
	    	$set = array(
          'qty' => $cartInfo->qty + $qty,
        );
        $message = $productInstance->checkQtyOfCommodiy_($pid, $set['qty']);
        if ($message) {
            $errorMessage = $message;  
	      } else {
	      	
	        if ($this->_cartInstance->updateCartProduct($cartInfo->cart_item_id, $set)) {
            callFunction('cart', 'after_add', array('pid' => $pid, 'qty' => $qty));
            }
	      }
	    }
  	}

  	if($isAjax === true){
  	  if($errorMessage !== ''){
  	    echo json_encode(array('success'=>false, 'message'=>$errorMessage));
  	  }else{
  	    $cartInstance = Cart_Model::getInstance();
        $cartInfo = $cartInstance->getCartProductList();
        $goodsInCart = isset($cartInfo->goods_number)? $cartInfo->goods_number : 0;
        $cart_item_ids = array();
        $cart_items = $cartInfo->product;
        $cart_item_id = $cart_items[0]->cart_item_id;
  	    echo json_encode(array('success'=>true, 'goodsInCart'=>$goodsInCart, 'cart_item_id' => $cart_item_id, 'cartcount' => $cartInstance->getCartCount() ));
  	  }
  	}else{
  	  if($errorMessage !== ''){
  	    setMessage($errorMessage, 'error');
  	  }else{
  	    gotourl('cart');
  	  }
  	}
  }

  public function deleteProductFromCartAction($cart_item_ids = null)
  {
  	$cart_item_ids = explode(',', $cart_item_ids);
  	$status = callFunction('cart', 'before_delete', $cart_item_ids);
    if (!isset($status) || $status) {
	    if ($this->_cartInstance->deleteCartProduct($cart_item_ids)) {
	    	$status = callFunction('cart', 'after_delete', $cart_item_ids);
// 	      setMessage(t('The selected item was successfully removed.'));
	    } else {
// 	      setMessage(t('Oops, it seems the selected item was not removed successfully. Please try again.', 'error'));
	    }
    }
    $reffer_url = isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "";
    header("Location: ".$reffer_url);
  }

  public function saveCartAction()
  {
  	if(isset($_POST['save']) || isset($_POST['save_x']) || isset($_POST['save_y'])) {
      $post = $_POST;
      if ($post['pids']) {
        foreach ($post['pids'] as $k => $v) {
          $set = array(
            'qty' => $post['qtys'][$k],
          );
          $this->_cartInstance->updateCartProduct($v, $set);
        }
      } else {
        setMessage(t('No goods'));
      }
      gotourl('cart');
    } else {
    	goto404(t('Access Denied'));
    }
  }

  public function fillOrderDeliveryInfo($rid, &$post)
  {
      $deliveryAddress = User_Model::getInstance()->getDeliveryRecordInfo($rid);
      if ($deliveryAddress == null)
      {
          return false;
      }
      $post['delivery_first_name'] = $deliveryAddress->delivery_first_name;
      $post['delivery_last_name'] = $deliveryAddress->delivery_last_name;
      $post['delivery_mobile'] = $deliveryAddress->delivery_mobile;
      $post['delivery_email'] = $deliveryAddress->delivery_email;
      $post['delivery_postcode'] = $deliveryAddress->delivery_postcode;
      $post['delivery_city'] = $deliveryAddress->delivery_city;
      $post['delivery_cid'] = $deliveryAddress->delivery_cid;
	  $post['delivery_country'] = $deliveryAddress->delivery_country;
	  $post['delivery_pid'] = $deliveryAddress->delivery_pid;
	  $post['delivery_province'] = $deliveryAddress->delivery_province;
      $post['delivery_address'] = $deliveryAddress->delivery_address;
      return true;
  }
  
  public function checkoutAction($pids = null, $fromcart = 1, $qty = null)
  {
  	global $user;
  	if (empty($user->uid)) {
  	    $_SESSION['redirect_url'] = 'cart/checkout';
  	    gotoUrl('user/login');
  	}
  	$orderInstance = Order_Model::getInstance();
  	$productInstance = Product_Model::getInstance();
  	$userInstance = User_Model::getInstance();
  	$siteInstance = Site_Model::getInstance();
  	if ($this->isPost())
  	{
  	    $post = $_POST;
  	    if (!$this->fillOrderDeliveryInfo($post['delivery_rid'], $post))
  	    {
  	        setMessage("failed to record delivery address", 'error');
  	        gotoUrl('cart');
  	    }
  		if (!$post['shipping_method']) {
  		  $shippingInstance = Shipping_Model::getInstance();
  		  $shippingList = $shippingInstance->shippingList(true);
  		  foreach ($shippingList as $k => $v) {
  		    if ($v->status == 1) {
  		      $post['shipping_method'] = $k;
  		      break;
  		    }
  		  }
  		}
  		//need calculate goods_weight, goods_amount(money_total), and goods_number first.
        
        if(!isset($pids)){
          $cart_item_ids = $post['cart_item_ids'];
        }else{
          $cart_item_ids = explode(',', $pids);
        }
        if (empty($cart_item_ids)) {
        	setMessage(t('There are no items in your shopping cart!'), 'error');
        	gotoUrl('cart');
        }
        $cartInfo = $this->_cartInstance->getCartProductList($cart_item_ids, null, null, false);
        if(empty($cartInfo->product)) {
        	$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
        	$oid = array_shift($orderList)->oid;
        	echo "<script>alert('The order has been submited! Redirect to your recent order page.');window.location.href='" . url('order/info/' . $oid .'/') . "';</script>";
        	return;
        }
        //we need to modify cu
        widgetCallFunction('weirddiscount', 'make_discount', array('cartInfo' => $cartInfo));
        
        
        //calculate shipping fee.
        $shipping_fee = 0.0;
        $fees = array();
  		if ($post['shipping_method']) {
  			$free_shipping = $this->isFreeShipping($cartInfo->goods_amount, $post['delivery_cid']);
  			if (!$free_shipping) {
				$shipping_money = $this->getshippingmoneyAction($post['shipping_method'], $cartInfo->goods_weight, 
				                                            $cartInfo->goods_amount, $cartInfo->goods_number, 
				                                            $post['delivery_cid'], $post['delivery_pid'], false);
    		  	if($shipping_money == -1 ){
    		  	 	setMessage(t('can not shipping!'), 'error');
  		  		 	gotoUrl('cart');
    		  	}elseif($shipping_money === 'error'){
    		  		setMessage(t('can not shipping!'), 'error');
  		  			gotoUrl('cart');
    		  	}
    		  	$shipping_fee = floatval($shipping_money);
  			}
  		}else{
  		  	setMessage(t('please select shipping method!'), 'error');
  		  	gotoUrl('cart');
  		}
  	  if (isset($shipping_fee)) {
  	    $fees['shipping']['fee_name'] = 'Shipping Fee';
        $fees['shipping']['fee_value'] = $shipping_fee;
      }
      //calculate bank fee.
      $paymentInstance = Payment_Model::getInstance();
      $payment = $post['payment_method'];
  	  if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) 
  	      && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) 
  	      && $paymentInfo->status) {
        //$fees['bankfee']['fee_value'] = calculateBankFee($paymentInfo->bankfee);
  	    /*
  	    if (isset($paymentInfo->bankfee) && $paymentInfo->bankfee) {
            $fees['bankfee']['fee_name'] = 'Bank Fee';
          if (strpos($paymentInfo->bankfee, '%')) {
            $paymentInfo->bankfee = floatval($paymentInfo->bankfee);
            $fees['bankfee']['fee_value'] = ceil_dec(($paymentInfo->bankfee * ($cartInfo->goods_amount +  $shipping_fee) / 100),2);
          } else {
            $paymentInfo->bankfee = floatval($paymentInfo->bankfee);
            $fees['bankfee']['fee_value'] = $paymentInfo->bankfee;
          }
        }*/
  	    $fees['bankfee']['fee_name'] = 'Bank Fee';
  	    $fees['bankfee']['fee_value'] = $this->calculateBankFee($paymentInfo->bankfee, $cartInfo->goods_amount, $shipping_fee);
      }

  		$orderToken = $orderInstance->getOrderToken();
  		if ($orderToken != $post['ordertoken']) {
  		  setMessage(t('Order token mistake!'), 'error');
  		  gotoUrl('cart');
  		}
  		$post['cart_item_ids'] = explode(',', $post['pids']);
  		if (!isset($post['cart_item_ids']) || !$post['cart_item_ids']) {
  		  setMessage(t('There are no items in your shopping cart!'), 'error');
  		  gotoUrl('cart');
  		}
  		/* chenzhigao removed for no need to save delivery record info;
  	    list($post['delivery_country'], $post['delivery_province']) = $siteInstance->getCountryProvincesNames($post['delivery_cid'], $post['delivery_pid']);
      	if(empty($post['delivery_province']) && $post['delivery_or_province'])
      	{
      		$post['delivery_province'] = $post['delivery_or_province'];
      	}
      	
  		$userInstance->saveDeliveryRecordInfo($post);
  		*/
  		//this function is not called now, in future, if it's implemented, maybe need more parameters.
  		//For we cut the $post to remove some vital value getting from the post.
  		$status = callFunction('order', 'before_add', $post);
      if (!isset($status) || $status) {
      	/*检查最大购买数和最小购买数*/
      	if (!isset($buytype)) {
      	  //TODO This might introduce incosistance for it use $post as parameter.
        	list($status, $message) = $productInstance->checkQtyOfCommodiy($post);
        	if (!$status) {
        	  setMessage($message, 'error');
        	  $reffer_url = $_SERVER["HTTP_REFERER"];
            header("Location: ".$reffer_url);
            exit;
        	}
        }
      	//$order = (object) $_POST;

      	//reset the post total_amount and post fees, to avoid hack from client side.
      	$post['total_amount'] = $cartInfo->goods_amount;
      	$post['fees'] = $fees;
      	$post['qtys'] = array();
      	foreach($cartInfo->product as $k => $v){
      		$post['qtys'][] = $v->qty;
      	}

        $postobj = (array) $post;
        widgetCallFunctionAll('order', 'before', $postobj);

        list($status, $oid) = $orderInstance->insertOrder($post);
        if($status === -3) {
          setMessage(t('Goods shortage!'), 'error');
          gotoUrl('cart');
        }  else if (!$status) {
          setMessage(t('Orders failure'), 'error');
          gotoUrl('cart');
        } else {
	  	  callFunction('order', 'after_add', array('oid' => $oid));
	  	  widgetCallFunctionAll('order', 'after', $postobj);
	  	
          setMessage(t('Congratulations, your order has been successfully placed!'), 'notice');
          
          $orderInstance->clearOrderToken();
          $orderInfo = $orderInstance->getOrderInfo($oid);

          if ($post['cart_item_ids']) {
            foreach ($post['cart_item_ids'] as $k => $v) {
              if ($post['fromcart']) {
                $this->_cartInstance->deleteCartProduct($v);
              }
            }
          }

          foreach ($orderInfo->items as $k => $v) {
            $orderInfo->goods_number += $v->qty;
          }
          
	  	/*发送下单邮件*/
	        $emailSetting = Bl_Config::get('orderTradingEmail');
	        
	        if (isset($emailSetting) && $emailSetting['status']) {
		  		$stmpSetting = Bl_Config::get('stmp');
		  		if (isset($stmpSetting) && $stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd'] && $post['delivery_email']) {
		  		    $mailInstance = new Mail_Model($stmpSetting);

		  		    $email[] = $post['delivery_email'];
	                $siteInfo = Bl_Config::get('siteInfo', array());
	                $emailSetting = Bl_Config::get("orderTradingEmail", array());
	                if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
	                	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
	                }
	                $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
	              	$emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
	              	
		  		    if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'], $user->nickname? $user->nickname : $user->name)) {
		  		      setMessage('Sending order email successfully.');
		             } else {
		              setMessage('Encounter error when sending email.', 'error');
		             }
		  		  } else {
		  			  setMessage('Mail server information is not configured properly, please check', 'error');
		  		  }
	  		}
	      }
      }
      
      
      if (isset($orderInfo)) {
        $status = callFunction('order', 'before_pay', $post);
        if (!isset($status) || $status) {
  		    $paymentInstance = Payment_Model::getInstance();
  		    $payment = $orderInfo->payment_method;
  		    if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) && $paymentInfo->status) {
  		      $info = $paymentInstance->getOrderPaymentInfo($orderInfo);
  		      if (hasFunction('paymentinfo')) {
  		        $info = callFunction('paymentinfo', $info);
  		      }
  		      $submitform = $instance->getSubmitForm($info);
  		    }
        }
      }
      $paymentInstance = Payment_Model::getInstance();
      $paymentList = $paymentInstance->getPaymentsList();
      $orderInfo->payment = $paymentList[$orderInfo->payment_method];
      $shippingInstance = Shipping_Model::getInstance();
      $shippingList = $shippingInstance->shippingList(true);
      $orderInfo->shipping = $shippingList[$orderInfo->shipping_method];
      gotoUrl('cart/ordersucc/' . $orderInfo->oid);
  	}
  	else {
  	  //If it is not post request.
  		if (!$pids) {
  			gotoUrl('cart');
  		}
  		$userInstance = User_Model::getInstance();
      if ($user->uid) {
	      $addressList = $userInstance->getDeliveryRecordList($user->uid);
	      if ($addressList) {
		      foreach($addressList as $k => $v) {
		      	if ($v->default) {
		          $address = $v;
		      	}
		      }
		      if (!isset($address)) {
		      	$address = $addressList[0];
		      }
	      }
      }
  	  $siteInstance = Site_Model::getInstance();
      $currencyList = $siteInstance->getCurrenciesList($visible = null);
      $shippingInstance = Shipping_Model::getInstance();
      $shippingList = $shippingInstance->shippingList(true);
      $paymentInstance = Payment_Model::getInstance();
      $paymentList = $paymentInstance->getPaymentsList(true);
      
      if ($fromcart) {
      	$pids = explode(',', $pids);
        $cartInfo = $this->_cartInstance->getCartProductList($pids, null, null, false);
        if(empty($cartInfo->product)) {
        	$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
        	$oid = array_shift($orderList)->oid;
        	echo "<script>alert('The order has been submited! Redirect to your recent order page.');window.location.href='" . url('order/info/' . $oid .'/') . "';</script>";
        	return;
        }
      } else {
        $cartInfo = new stdClass();
        $productInfo = $productInstance->getProductInfo($pids);
        $cartInfo->product[0] = $productInfo;
        $cartInfo->product[0]->qty = $qty;
        if (!$productInfo->free_shipping) {
          $cartInfo->goods_number = $qty;
          $cartInfo->goods_weight = $productInfo->wt * $qty;
        }
        $cartInfo->goods_amount = $productInfo->price * $qty;
      }
      
      
      /* 检查库存 */
      if (Bl_Config::get('stockCheck', false)) {
        if (isset($cartInfo->product) && $cartInfo->product) {
          $check_pids = array();
          $check_qtys = array();
          foreach($cartInfo->product as $item) {
            if (!$item->free_shipping) {
              $check_pids[] = $item->pid;
              $check_qtys[] = $item->qty;
            }
          }
          $pidqty = $orderInstance->mergerRepetitive($check_pids, $check_qtys);
          if (isset($pidqty) && $pidqty) {
            foreach ($pidqty as $k => $qty) {
              if (!$productInstance->checkProductStock($k, $qty)) {
                setMessage(t('understock, Merchandise ') . $item->name . t(' inventory for ') . $item->stock, 'error');
                gotoUrl('cart');
              }
            }
          }
        }
      }
      $siteInstance = Site_Model::getInstance();
      $countries = $siteInstance->getCountries();
      $cid = isset($address->delivery_cid) && $address->delivery_cid ? $address->delivery_cid : Bl_Config::get('payment.country', key($countries));
      $provinces = $siteInstance->getProvinces(key($countries));
      $orderToken = $orderInstance->saveOrderToken();
      $this->view->assign('ordertoken', $orderToken);
      $this->view->render('checkout.phtml', array(
        'cartInfo' => isset($cartInfo) ? $cartInfo : array(),
        'currencyList' => isset($currencyList) ? $currencyList : null,
        'shippingList' => isset($shippingList) ? $shippingList : null,
        'paymentList' => isset($paymentList) ? $paymentList : null,
        'fromcart' => isset($fromcart) ? $fromcart : 0,
	  	'addressList' => isset($addressList) ? $addressList : null,
	  	//'address' => isset($address) ? $address : null,
        'cid' => $cid,
	    'countries' => $countries,
	    'provinces' => $provinces,
        'integralConfig' => Bl_Config::get('widget.integral.config', null),
        'pids' => implode(',', $pids)
      ));
  	}
  }

  public function ordersuccAction ($oid)
  {
  	global $user;
  	if ($user->uid <= 0) {
  		gotoUrl('user/login');
  	}
    $submitform = null;
    //maybe the order has already been changed. So may need to update the $orderInfo.
    
    $orderInstance = Order_Model::getInstance();
    $orderInfo = $orderInstance->getOrderInfo($oid);
    
    if (empty($orderInfo) || $user->uid != $orderInfo->uid) {
    	goto404('Order not found');
    }
    $paymentInstance = Payment_Model::getInstance();
    $payment = $orderInfo->payment_method;
    if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) && $paymentInfo->status) {
        $info = $paymentInstance->getOrderPaymentInfo($orderInfo);
        if (hasFunction('paymentinfo')) {
          $info = callFunction('paymentinfo', $info);
        }
        $submitform = $instance->getSubmitForm($info);
    }
    if (!$orderInfo) {
      setMessage(t('Operation Timeout'), 'notice');
    } else {
    	$message = getMessages();
    	if ($message) {
    		$message = $message[0];
    	  $_SESSION['omessage'] = $message;
    	} else if(isset($_SESSION['omessage'])) {
    		$message = $_SESSION['omessage'];
    	}
    	setMessage($message['message'], $message['type']);
    }
    $this->view->render('checkoutsucceed.phtml', array(
        'submitform' => isset($submitform) ? $submitform : null,
        'orderInfo' => isset($orderInfo) ? $orderInfo : null,
      ));
  }

  public function calculateBankFee($paymentFee, $goodsAmount, $shippingFee) {
  	if (!isset($paymentFee)) {
  		return -1;
  	}
  	$index = strpos($paymentFee, '%');
  	if ($index > 0) {
  		$rateFee = floatval($paymentFee);
        $constFee = 0.0;
        if ($index < strlen($paymentFee) - 1) {
        	$constFee = floatval(substr($paymentFee, $index + 1));
        }
        $bank_fee = ceil_dec($rateFee * ($goodsAmount +  $shippingFee) / 100, 2) + $constFee;
    }
    else {
    	$bank_fee = floatval($paymentFee);
    }
    return $bank_fee;
  }
  
  public function ajaxGetFeesAction($pids, $fee_names, $cid = null, $pid = null){
      if (!isset($cid) || !isset($pid)){
          exit -1;
      }
      $cart_item_ids = explode(',', $pids);
      list($shipping_name, $payment_name) = explode(',', $fee_names);
      $cartInfo = $this->_cartInstance->getCartProductList($cart_item_ids, null, null, false);
      
      $shipping_fee = $this->getshippingmoneyAction($shipping_name, $cartInfo->goods_weight, 
				                                      $cartInfo->goods_amount, $cartInfo->goods_number, 
				                                      $cid, $pid, true);
      $paymentInstance = Payment_Model::getInstance();
      
      $payment = $payment_name;
      $bank_fee = -1;
      
      if(($payment == 'not_set')){
        $bank_fee = 0.0;
      }
  	  else if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) 
  	      && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) 
  	      && $paymentInfo->status) {
  	    $bank_fee = $this->calculateBankFee($paymentInfo->bankfee, $cartInfo->goods_amount, $shipping_fee);
      }else{
        $bank_fee = -1;
        //setMessage('Not supported payment method, please check your settings.', 'error');
      }
      
      $payment_amount = $cartInfo->goods_amount;
      $free_shipping = $this->isFreeShipping($cartInfo->goods_amount, $cid);
      if($shipping_fee >= 0 && !$free_shipping){
        $payment_amount += $shipping_fee;
      }
      if($bank_fee >= 0){
        $payment_amount += $bank_fee;
      }
      
      echo json_encode(array('shipping_fee'=>$shipping_fee, 
      							'shipping_fee_c'=>c($shipping_fee),
      							'free_shipping' => $free_shipping,
      							'bank_fee'=>$bank_fee,
                                'bank_fee_c'=>c($bank_fee), 
       							'payment_amount'=>$payment_amount,
                                'payment_amount_c'=>c($payment_amount)));
  }
  
  public function getshippingmoneyAction($shipping_name, $goods_weight, $goods_amount, 
                                         $goods_number, $cid, $pid = null, $isajax = true)
  {
    $configure = Bl_Plugin::getInstance('shipping', $shipping_name);
    $configure = Bl_Config::get('shipping.'.$shipping_name, 0);
    $pid == 0 ? $pid = 'null' : 1;
    $i = 0;
    if (isset($configure['setting'])) {
      foreach ($configure['setting'] as $k => $v) {
        if (array_key_exists('0', $v['area'])) {
          $configure = $v;
          $i ++;
          break;
        } else if (array_key_exists($cid, $v['area'])) {
      		if (in_array($pid, $v['area'][$cid]) || in_array('null', $v['area'][$cid]) || in_array('0', $v['area'][$cid])) {
      			$configure = $v;
      			$i ++;
      			break;
      		}
      	}
      }
    }
    if ($i == 0) {
    	if($isajax){
    		echo '-1';
    	}else{
    		return -1;
    	}
    	exit;
    }
    $shippingInstance = Bl_Plugin::getInstance('shipping', $shipping_name);
    $shippingInstance->initialize($configure);
    $shipping_money = $shippingInstance->calculate($goods_weight, $goods_amount, $goods_number);
    $shipping_money_new = callFunction('getShippingMoney', $shipping_name, $goods_weight, $goods_amount, $goods_number, $cid, $pid);
    if (isset($shipping_money_new) && $shipping_money_new) {
      $shipping_money = $shipping_money_new;
    }
    if (! isset($shipping_money)) {
     	$shipping_money = 'error';
    }
    return $shipping_money;
    /*
    if($isajax){
    	echo $shipping_money;
    }else{
    	return $shipping_money;
    }
    exit;*/
  }
  
  
  public function ajaxgetpaymentmoneyAction($payment, $goods_amount = 0) {
    $paymentInstance = Payment_Model::getInstance();
      if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) && $paymentInfo->status) {
        if (isset($paymentInfo->bankfee) && $paymentInfo->bankfee) {
          if (strpos($paymentInfo->bankfee, '%')) {
            $paymentInfo->bankfee = floatval($paymentInfo->bankfee);
            $payment_money = $paymentInfo->bankfee * $goods_amount / 100;
          } else {
            $paymentInfo->bankfee = floatval($paymentInfo->bankfee);
            $payment_money = $paymentInfo->bankfee;
          }
        }
        $payment_money_new = callFunction('getPaymentMoney', $payment, $goods_amount);
        if (isset($payment_money_new) && $payment_money_new) {
          $payment_money = $payment_money_new;
        }
      }
      echo isset($payment_money) ? $payment_money : 0;
  }

  /**
   * 修改购物车中商品信息
   * @param unknown_type $pid
   * @param unknown_type $post
   */
  public function removeOneProductAction($cart_item_id)
  {
  	$cartProductInfo = $this->_cartInstance->getCartProductInfo($cart_item_id);
  	if($cartProductInfo && $cartProductInfo->qty > 1){
  		$newQty = $cartProductInfo->qty - 1;
  		$this->_cartInstance->updateCartProduct($cart_item_id, array('qty' => $newQty));
  	}else{
  		$this->deleteProductFromCartAction($cart_item_id);
  	}
    $reffer_url = $_SERVER["HTTP_REFERER"];
    header("Location: ".$reffer_url);
  }
  public function updateOrderProductQtyAction($cart_item_id, $qty)
  {
    $productInstance = Product_Model::getInstance();
    $delta = $this->_cartInstance->updateCartProduct($cart_item_id, array('qty' => $qty));
//    echo $delta;
    if($delta == -1){
      echo json_encode(array('error'=>'Product Shortage'));
    }else{
      //update success.
      //first get the info of the changed cart_item_id.
      $productInstance = Product_Model::getInstance();
      $cartInfo = $this->_cartInstance->getCartProductList(null, null, null, false);
      $cartProductList = isset($cartInfo->product) ? $cartInfo->product : array();
      $newarr = array();
      if ($cartProductList) {
        $total = 0;
        foreach ($cartProductList as $k => $v) {
          if($cart_item_id == $v->cart_item_id){
            $modified_item = array(
              'cart_item_id' => $v->cart_item_id,
              'quantity' => $v->qty,
              'price' => c($v->price),
              'list_price' => c($v->list_price),
              'subtotal' => c($v->price * $v->qty),
            );
            break;
          }
        }
        $newarr = array(
          'total' => isset($cartInfo->goods_number) ? $cartInfo->goods_number : 0,
          'amount' => c($cartInfo->goods_amount),
          'saving_amount' => c($cartInfo->total_save_amount),
          'weight' => isset($cartInfo->goods_weight) ? $cartInfo->goods_weight : 0,
          'modified_item' => $modified_item,
        );
      }
      echo json_encode($newarr);
    }
  }
  
  /**
   * 检测积分
   * return param status 
   * -1 输入积分必须是整数
   * 0 金额必需是数字
   * 1 会员积分不够
   * 2 输入积分大于本订单限用积分
   * 3 可以兑换
   * price 用户输入积分转换价格
   * Enter description here ...
   * @param unknown_type $amount
   */
  public function ajaxCheckIntegralAction($amount = 0, $inputIntegral = 0)
  {
  	$newArr = array(
  		'status' => 0,
  		'price' => 0,
  		'userIntegral' => 0,
  		'limitIntegral' => 0
  	);
  	$flag = true;
  	if(!$amount || !is_numeric($amount)){
  		$newArr['status'] = 0;
  		$flag = false;
  	};
  	if(!$inputIntegral || !is_numeric($inputIntegral)){echo '-1';exit;};
  	$userInstance = User_Model::getInstance();
  	$integral = $userInstance->getUserIntegral();
  	$tempIntegral = 0;
  	if($integral == false){
  		$newArr['status'] = 1;
  		$flag = false;
  	}else{
  		$newArr['userIntegral'] = $integral;
  		$integralConfig = Bl_Config::get('widget.integral.config', NULL);
  		if($integralConfig)
  		{
  			$tempIntegral = $integralConfig['conversionrate'] ? $amount * $integralConfig['conversionrate'] : $amount;
  			$tempIntegral = $integralConfig['proportion'] ? $tempIntegral * $integralConfig['proportion']/100 : $tempIntegral;
  			$tempIntegral = ceil($tempIntegral);
  			$newArr['limitIntegral'] = $tempIntegral;
  			if($integralConfig['conversionrate'] && is_numeric($integralConfig['conversionrate'])){
  				$newArr['price'] = $inputIntegral / $integralConfig['conversionrate'];
  			}
  		}
  	}
  	if($inputIntegral>$tempIntegral){
  		$newArr['status'] = 2;
  		$newArr['price'] = 0;
  		$flag = false;
  	}
  	if($integral<$tempIntegral){
  		$newArr['status'] = 1;
  		$newArr['price'] = 0;
  		$flag = false;
  	}	
		if($flag){
			$newArr['status'] = 3;
		}
		echo json_encode($newArr);
  }
 
  public function isFreeShipping($goodsAmount, $cid) {
  	return $goodsAmount >= 119 && ($cid == 1 || $cid == 2);
  }
  
  public function paytransferAction(){
  	if($this->isPost()){
  		global $user;
  		if (empty($user->uid)) {
  			$_SESSION['redirect_url'] = 'cart/checkout';
  			gotoUrl('user/login');
  		}
  		
  		$orderInstance = Order_Model::getInstance();
  		$productInstance = Product_Model::getInstance();
  		$userInstance = User_Model::getInstance();
  		$siteInstance = Site_Model::getInstance();
  		
  		$post = $_POST;
  		$pids = $post['pids'];
  		
  	    if (!$this->fillOrderDeliveryInfo($post['delivery_rid'], $post))
  	    {
  	        setMessage("failed to record delivery address", 'error');
  	        gotoUrl('cart');
  	    }
  		if (!$post['shipping_method']) {
  		  $shippingInstance = Shipping_Model::getInstance();
  		  $shippingList = $shippingInstance->shippingList(true);
  		  foreach ($shippingList as $k => $v) {
  		    if ($v->status == 1) {
  		      $post['shipping_method'] = $k;
  		      break;
  		    }
  		  }
  		}
  		//need calculate goods_weight, goods_amount(money_total), and goods_number first.
        
        if(!isset($pids)){
          $cart_item_ids = $post['cart_item_ids'];
        }else{
          $cart_item_ids = explode(',', $pids);
        }
        if (empty($cart_item_ids)) {
        	log::save('debug', 'make_order_process', 'no itemid');
        	setMessage(t('There are no items in your shopping cart!'), 'error');
        	gotoUrl('cart');
//         	$this->view->render("testpage.phtml", array(
//         		'e' => 'no itemid'
//         	));
        }
        $cartInfo = $this->_cartInstance->getCartProductList($cart_item_ids, null, null, false);
        if(empty($cartInfo->product)) {
        	$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
        	$oid = array_shift($orderList)->oid;
        	log::save('debug', 'make_order_process', 'itemid error');
        	echo "<script>alert('The order has been submited! Redirect to your user center.');window.location.href='" . url('user/myaccount/') . "';</script>";
        	return;
//         	$this->view->render("testpage.phtml", array(
//         		'e' => 'itemid error'
//         	));
        }
        //we need to modify cu
        widgetCallFunction('weirddiscount', 'make_discount', array('cartInfo' => $cartInfo));
        
        
        //calculate shipping fee.
        $shipping_fee = 0.0;
        $fees = array();
  		if ($post['shipping_method']) {
  			$free_shipping = $this->isFreeShipping($cartInfo->goods_amount, $post['delivery_cid']);
  			if (!$free_shipping) {
				$shipping_money = $this->getshippingmoneyAction($post['shipping_method'], $cartInfo->goods_weight, 
				                                            $cartInfo->goods_amount, $cartInfo->goods_number, 
				                                            $post['delivery_cid'], $post['delivery_pid'], false);
    		  	if($shipping_money == -1 ){
    		  		log::save('debug', 'make_order_process', 'ship error1');
//     		  	 	$this->view->render("testpage.phtml", array(
// 		        		'e' => 'ship error1'
// 		        	));
    		  	}elseif($shipping_money === 'error'){
    		  		log::save('debug', 'make_order_process', 'ship error2');
//     		  		$this->view->render("testpage.phtml", array(
// 		        		'e' => 'ship error2'
// 		        	));
    		  	}
    		  	$shipping_fee = floatval($shipping_money);
  			}
  		}else{
  			log::save('debug', 'make_order_process', 'need shipping method');
//   		  	$this->view->render("testpage.phtml", array(
// 		        		'e' => 'need shipping method'
// 		        	));
  		}
  	  if (isset($shipping_fee)) {
  	    $fees['shipping']['fee_name'] = 'Shipping Fee';
        $fees['shipping']['fee_value'] = $shipping_fee;
      }
      //calculate bank fee.
      $paymentInstance = Payment_Model::getInstance();
      $payment = $post['payment_method'];
  	  if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) 
  	      && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) 
  	      && $paymentInfo->status) {
        //$fees['bankfee']['fee_value'] = calculateBankFee($paymentInfo->bankfee);
  	    /*
  	    if (isset($paymentInfo->bankfee) && $paymentInfo->bankfee) {
            $fees['bankfee']['fee_name'] = 'Bank Fee';
          if (strpos($paymentInfo->bankfee, '%')) {
            $paymentInfo->bankfee = floatval($paymentInfo->bankfee);
            $fees['bankfee']['fee_value'] = ceil_dec(($paymentInfo->bankfee * ($cartInfo->goods_amount +  $shipping_fee) / 100),2);
          } else {
            $paymentInfo->bankfee = floatval($paymentInfo->bankfee);
            $fees['bankfee']['fee_value'] = $paymentInfo->bankfee;
          }
        }*/
  	    $fees['bankfee']['fee_name'] = 'Bank Fee';
  	    $fees['bankfee']['fee_value'] = $this->calculateBankFee($paymentInfo->bankfee, $cartInfo->goods_amount, $shipping_fee);
      }

  		$orderToken = $orderInstance->getOrderToken();
  		if ($orderToken != $post['ordertoken']) {
  			log::save('debug', 'make_order_process','token differ post_token');
  			setMessage(t('Order token mistake!'), 'error');
  			gotoUrl('cart');
//   		  $this->view->render("testpage.phtml", array(
// 		        		'e' => 'token differ post_token'
// 		        	));
  		}
  		$post['cart_item_ids'] = explode(',', $post['pids']);
  		if (!isset($post['cart_item_ids']) || !$post['cart_item_ids']) {
  			log::save('debug', 'make_order_process','no itemid2');
//   		  $this->view->render("testpage.phtml", array(
// 		        		'e' => 'no itemid2'
// 		        	));
  		}
  		/* chenzhigao removed for no need to save delivery record info;
  	    list($post['delivery_country'], $post['delivery_province']) = $siteInstance->getCountryProvincesNames($post['delivery_cid'], $post['delivery_pid']);
      	if(empty($post['delivery_province']) && $post['delivery_or_province'])
      	{
      		$post['delivery_province'] = $post['delivery_or_province'];
      	}
      	
  		$userInstance->saveDeliveryRecordInfo($post);
  		*/
  		//this function is not called now, in future, if it's implemented, maybe need more parameters.
  		//For we cut the $post to remove some vital value getting from the post.
  		$status = callFunction('order', 'before_add', $post);
      if (!isset($status) || $status) {
      	/*检查最大购买数和最小购买数*/
      	if (!isset($buytype)) {
      	  //TODO This might introduce incosistance for it use $post as parameter.
        	list($status, $message) = $productInstance->checkQtyOfCommodiy($post);
        	if (!$status) {
//         	  $this->view->render("testpage.phtml", array(
// 		        		'e' => 'error'
// 		        	));
        	}
        }
      	//$order = (object) $_POST;

      	//reset the post total_amount and post fees, to avoid hack from client side.
      	$post['total_amount'] = $cartInfo->goods_amount;
      	$post['fees'] = $fees;
      	$post['qtys'] = array();
      	foreach($cartInfo->product as $k => $v){
      		$post['qtys'][] = $v->qty;
      	}

        $postobj = (array) $post;
        widgetCallFunctionAll('order', 'before', $postobj);

        
        list($status, $oid) = $orderInstance->insertOrder($post);
        if($status === -3) {
//           $this->view->render("testpage.phtml", array(
// 		        		'e' => 'not enough'
// 		        	));
        }  else if (!$status) {
//           $this->view->render("testpage.phtml", array(
// 		        		'e' => 'error'
// 		        	));
        } else {
	  	  callFunction('order', 'after_add', array('oid' => $oid));
	  	  widgetCallFunctionAll('order', 'after', $postobj);
	  	

          
          $orderInstance->clearOrderToken();
          $orderInfo = $orderInstance->getOrderInfo($oid);

          if ($post['cart_item_ids']) {
            foreach ($post['cart_item_ids'] as $k => $v) {
              if ($post['fromcart']) {
                $this->_cartInstance->deleteCartProduct($v);
              }
            }
          }

          foreach ($orderInfo->items as $k => $v) {
            $orderInfo->goods_number += $v->qty;
          }
          
	  	/*发送下单邮件*/
          $emailSetting = Bl_Config::get('orderTradingEmail');
           
          if (isset($emailSetting) && $emailSetting['status']) {
          	$stmpSetting = Bl_Config::get('stmp');
          	if (isset($stmpSetting) && $stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd'] && $post['delivery_email']) {
          		$mailInstance = new Mail_Model($stmpSetting);
          
          		$email[] = $post['delivery_email'];
          		$siteInfo = Bl_Config::get('siteInfo', array());
          		$emailSetting = Bl_Config::get("orderTradingEmail", array());
          		if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
          			$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          		}
          		$emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
          		$emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
          
          		if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'], $user->nickname? $user->nickname : $user->name)) {
          			setMessage('Sending order email successfully.');
          		} else {
          			setMessage('Encounter error when sending email.', 'error');
          		}
          	} else {
          		setMessage('Mail server information is not configured properly, please check', 'error');
          	}
          }
	      }
      }
      
      
      if (isset($orderInfo)) {
        $status = callFunction('order', 'before_pay', $post);
        if (!isset($status) || $status) {
  		    $paymentInstance = Payment_Model::getInstance();
  		    $payment = $orderInfo->payment_method;
  		    if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) && $paymentInfo->status) {
  		      $info = $paymentInstance->getOrderPaymentInfo($orderInfo);
  		      if (hasFunction('paymentinfo')) {
  		        $info = callFunction('paymentinfo', $info);
  		      }
  		      $submitform = $instance->getSubmitForm($info);
  		    }
        }
      }
      $paymentInstance = Payment_Model::getInstance();
      $paymentList = $paymentInstance->getPaymentsList();
      $orderInfo->payment = $paymentList[$orderInfo->payment_method];
      $shippingInstance = Shipping_Model::getInstance();
      $shippingList = $shippingInstance->shippingList(true);
      $orderInfo->shipping = $shippingList[$orderInfo->shipping_method];
      
      
      
      
      
      $payment = $orderInfo->payment_method;
      if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) && $paymentInfo->status) {
      	$info = $paymentInstance->getOrderPaymentInfo($orderInfo);
      	if (hasFunction('paymentinfo')) {
      		$info = callFunction('paymentinfo', $info);
      	}
      	$submitform = $instance->getSubmitForm($info);
      }
      
      
      $this->view->render("paytransferpage.phtml", array(
	                    'orderInfo' => $orderInfo,
		        		'submitform' => $submitform,
		        	));
  		
  	}
  	else{
  		goto404();
  	}

  }
  
  
  function mobilecartpostAction(){
  	if ($this->isPost()) {
  		$post = $_POST;
  		if (isset($post['checkout'])) {
  			if (!isset($post['cart_item_id_checked'])) {
  				gotoUrl('cart/getcarinfo');
  			}
  			$pids = implode(",", $post['cart_item_id_checked']);
  			// added by chenzhigao for cart item comment
  			foreach ($post['cart_item_requirement'] as $cart_item_id => $comment) {
  				if ($comment != "If you have any other requirements for this item, please feel free to leave a message here.") {
  					if (Cart_Model::getInstance()->addCartProductComment($cart_item_id, $comment) <= 0) {
  						log::save('debug', 'add cart product comment failed', array($cart_item_id, $comment));
  					}
  				}
  			}
//   			gotourl("cart/mobileselectaddress/" . $pids);
  			gotourl('cart/mobilecheckout/'.$pids);
  		}else{
  			$reffer_url = $_SERVER["HTTP_REFERER"];
  			header("Location: ".$reffer_url);
  		}
  	} else {
  		goto404(t('Access Denied'));
  	}
  }
  
  function mobileselectaddressAction($pids = null){
  	global $user;
  	if (empty($user->uid)) {
  		$_SESSION['redirect_url'] = 'cart/mobileselectaddress/' . $pids;
  		gotoUrl('user/login');
  	}
  	
  	$originpids = $pids;
  	$pids = explode(',', $pids);
  	$cartInfo = $this->_cartInstance->getCartProductList($pids, null, null, false);
  	if(empty($cartInfo->product)) {
  		$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
  		$oid = array_shift($orderList)->oid;
  		echo "<script>alert('The order has been submited! Redirect to your recent order page.');window.location.href='" . url('order/info/' . $oid .'/') . "';;</script>";
  		return;
  	}

  	if (Bl_Config::get('stockCheck', false)) {
  		if (isset($cartInfo->product) && $cartInfo->product) {
  			$check_pids = array();
  			$check_qtys = array();
  			foreach($cartInfo->product as $item) {
  				if (!$item->free_shipping) {
  					$check_pids[] = $item->pid;
  					$check_qtys[] = $item->qty;
  				}
  			}
  			$pidqty = $orderInstance->mergerRepetitive($check_pids, $check_qtys);
  			if (isset($pidqty) && $pidqty) {
  				foreach ($pidqty as $k => $qty) {
  					if (!$productInstance->checkProductStock($k, $qty)) {
  						setMessage(t('understock, Merchandise ') . $item->name . t(' inventory for ') . $item->stock, 'error');
  						gotoUrl('cart');
  					}
  				}
  			}
  		}
  	}
  	
  	$userInstance = User_Model::getInstance();
  	if ($user->uid) {
  		$addressList = $userInstance->getDeliveryRecordList($user->uid);
  		if ($addressList) {
  			foreach($addressList as $k => $v) {
  				if ($v->default) {
  					$address = $v;
  				}
  			}
  			if (!isset($address)) {
  				$address = $addressList[0];
  			}
  		}
  	}
  	
  	$this->view->render("checkout_address.phtml", array(
  		'pids' => $originpids,
//   		'cartInfo' => isset($cartInfo) ? $cartInfo : array(),
  		'addressList' => isset($addressList) ? $addressList : null,
  	));
  }
  
  function mobileselectshippingAction($pids = null){
  	global $user;
  	if (empty($user->uid)) {
  		$_SESSION['redirect_url'] = 'cart/mobileselectshipping/' . $pids;
  		gotoUrl('user/login');
  	}
  	
  	$originpids = $pids;
  	$pids = explode(',', $pids);
  	$cartInfo = $this->_cartInstance->getCartProductList($pids, null, null, false);
  	if(empty($cartInfo->product)) {
  		$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
  		$oid = array_shift($orderList)->oid;
  		echo "<script>alert('The order has been submited! Redirect to your recent order page.');window.location.href='" . url('order/info/' . $oid .'/') . "';;</script>";
  		return;
  	}
  	
  	if (Bl_Config::get('stockCheck', false)) {
  		if (isset($cartInfo->product) && $cartInfo->product) {
  			$check_pids = array();
  			$check_qtys = array();
  			foreach($cartInfo->product as $item) {
  				if (!$item->free_shipping) {
  					$check_pids[] = $item->pid;
  					$check_qtys[] = $item->qty;
  				}
  			}
  			$pidqty = $orderInstance->mergerRepetitive($check_pids, $check_qtys);
  			if (isset($pidqty) && $pidqty) {
  				foreach ($pidqty as $k => $qty) {
  					if (!$productInstance->checkProductStock($k, $qty)) {
  						setMessage(t('understock, Merchandise ') . $item->name . t(' inventory for ') . $item->stock, 'error');
  						gotoUrl('cart');
  					}
  				}
  			}
  		}
  	}
  	
  	$shippingInstance = Shipping_Model::getInstance();
  	$shippingList = $shippingInstance->shippingList(true);
  	
  	$this->view->render('checkout_shipping.phtml', array(
  		'pids' => $originpids,
//   		'cartInfo' => isset($cartInfo) ? $cartInfo : array(),
  		'shippingList' => isset($shippingList) ? $shippingList : null,
  	));
  }
  
  function mobileselectpaymethodAction($pids = null){
  	global $user;
  	if (empty($user->uid)) {
  		$_SESSION['redirect_url'] = 'cart/mobileselectpaymethod/' . $pids;
  		gotoUrl('user/login');
  	}
  	
  	$originpids = $pids;
  	$pids = explode(',', $pids);
  	$cartInfo = $this->_cartInstance->getCartProductList($pids, null, null, false);
  	if(empty($cartInfo->product)) {
  		$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
  		$oid = array_shift($orderList)->oid;
  		echo "<script>alert('The order has been submited! Redirect to your recent order page.');window.location.href='" . url('order/info/' . $oid .'/') . "';;</script>";
  		return;
  	}
  	 
  	if (Bl_Config::get('stockCheck', false)) {
  		if (isset($cartInfo->product) && $cartInfo->product) {
  			$check_pids = array();
  			$check_qtys = array();
  			foreach($cartInfo->product as $item) {
  				if (!$item->free_shipping) {
  					$check_pids[] = $item->pid;
  					$check_qtys[] = $item->qty;
  				}
  			}
  			$pidqty = $orderInstance->mergerRepetitive($check_pids, $check_qtys);
  			if (isset($pidqty) && $pidqty) {
  				foreach ($pidqty as $k => $qty) {
  					if (!$productInstance->checkProductStock($k, $qty)) {
  						setMessage(t('understock, Merchandise ') . $item->name . t(' inventory for ') . $item->stock, 'error');
  						gotoUrl('cart');
  					}
  				}
  			}
  		}
  	}
  	
  	$this->view->render("checkout_payment.phtml", array(
  		'pids' => $originpids,
//   		'cartInfo' => isset($cartInfo) ? $cartInfo : array(),
  	));
  }
  
  
  function mobilecheckoutAction($pids = null, $fromcart = 1, $qty = null){
  	global $user;
  	if (empty($user->uid)) {
  		$_SESSION['redirect_url'] = 'cart/mobilecheckout';
  		gotoUrl('user/login');
  	}
  	$orderInstance = Order_Model::getInstance();
  	$productInstance = Product_Model::getInstance();
  	$userInstance = User_Model::getInstance();
  	$siteInstance = Site_Model::getInstance();
  	
  	
  	//If it is not post request.
  	if (!$pids) {
  		gotoUrl('cart');
  	}
  	$userInstance = User_Model::getInstance();
  	if ($user->uid) {
  		$addressList = $userInstance->getDeliveryRecordList($user->uid);
  		if ($addressList) {
  			foreach($addressList as $k => $v) {
  				if ($v->default) {
  					$address = $v;
  				}
  			}
  			if (!isset($address)) {
  				$address = $addressList[0];
  			}
  		}
  	}
  	$siteInstance = Site_Model::getInstance();
  	$currencyList = $siteInstance->getCurrenciesList($visible = null);
  	$shippingInstance = Shipping_Model::getInstance();
  	$shippingList = $shippingInstance->shippingList(true);
  	$paymentInstance = Payment_Model::getInstance();
  	$paymentList = $paymentInstance->getPaymentsList(true);
  	
  	if ($fromcart) {
  		$pids = explode(',', $pids);
  		$cartInfo = $this->_cartInstance->getCartProductList($pids, null, null, false);
  		if(empty($cartInfo->product)) {
  			$orderList = Order_Model::getInstance()->getOrdersList(array('uid' => $user->uid), 1, 1);
  			$oid = array_shift($orderList)->oid;
  			echo "<script>alert('The order has been submited! Redirect to your recent order page.');window.location.href='" . url('order/info/' . $oid .'/') . "';;</script>";
  			return;
  		}
  	} else {
  		$cartInfo = new stdClass();
  		$productInfo = $productInstance->getProductInfo($pids);
  		$cartInfo->product[0] = $productInfo;
  		$cartInfo->product[0]->qty = $qty;
  		if (!$productInfo->free_shipping) {
  			$cartInfo->goods_number = $qty;
  			$cartInfo->goods_weight = $productInfo->wt * $qty;
  		}
  		$cartInfo->goods_amount = $productInfo->price * $qty;
  	}
  	
  	
  	/* 检查库存 */
  	if (Bl_Config::get('stockCheck', false)) {
  		if (isset($cartInfo->product) && $cartInfo->product) {
  			$check_pids = array();
  			$check_qtys = array();
  			foreach($cartInfo->product as $item) {
  				if (!$item->free_shipping) {
  					$check_pids[] = $item->pid;
  					$check_qtys[] = $item->qty;
  				}
  			}
  			$pidqty = $orderInstance->mergerRepetitive($check_pids, $check_qtys);
  			if (isset($pidqty) && $pidqty) {
  				foreach ($pidqty as $k => $qty) {
  					if (!$productInstance->checkProductStock($k, $qty)) {
  						setMessage(t('understock, Merchandise ') . $item->name . t(' inventory for ') . $item->stock, 'error');
  						gotoUrl('cart');
  					}
  				}
  			}
  		}
  	}
  	$siteInstance = Site_Model::getInstance();
  	$countries = $siteInstance->getCountries();
  	$cid = isset($address->delivery_cid) && $address->delivery_cid ? $address->delivery_cid : Bl_Config::get('payment.country', key($countries));
  	$provinces = $siteInstance->getProvinces(key($countries));
  	$orderToken = $orderInstance->saveOrderToken();
  	$this->view->assign('ordertoken', $orderToken);
  	
  	$this->view->setTitle('Checkout | Adoringdress');
  	
  	$this->view->render('checkout.phtml', array(
  			'cartInfo' => isset($cartInfo) ? $cartInfo : array(),
  			'currencyList' => isset($currencyList) ? $currencyList : null,
  			'shippingList' => isset($shippingList) ? $shippingList : null,
  			'paymentList' => isset($paymentList) ? $paymentList : null,
  			'fromcart' => isset($fromcart) ? $fromcart : 0,
  			'addressList' => isset($addressList) ? $addressList : null,
  			//'address' => isset($address) ? $address : null,
  			'cid' => $cid,
  			'countries' => $countries,
  			'provinces' => $provinces,
  			'integralConfig' => Bl_Config::get('widget.integral.config', null),
  			'pids' => is_array($pids) ? implode(',', $pids) : $pids
  	));
  	
  }
  
}