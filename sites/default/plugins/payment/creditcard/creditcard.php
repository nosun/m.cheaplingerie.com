<?php
class CreditCard extends Payment_Abstract implements Payment_Interface, Payment_Server_Interface
{
  public function getPaymentName()
  {
    return 'creditcard';
  }

  public function getSettingFields()
  {
    return array(
    		'merNo' => '商户号',
      		'gatewayNo' => '网关接入号',
    		'gateway3DNo' => '3D网关接入号',
		'signKey' => '签名key',
		'signKey3D' => '3D签名key',
    		'submitUrl' => '提交地址',
		'use3Dgate' => array(
			'name' => '是否使用3D通道',
    			'type' => 'select',
    			'options' => array(
    				0 => '否',
    				1 => '是',
    			),
  			'default' => 0, 	
    		),
    		'currency' => array(
        		'name' => '支付货币',
        		'type' => 'select',
        		'options' => array(
		          'USD' => '美元',
		          'AUD' => '澳元',
		          'CAD' => '加元',
		          'EUR' => '欧元',
		          'GBP' => '英镑',
		          'JPY' => '日元',
		          'HKD' => '港元',
        		),
        		'default' => 'USD',
      		),
    );
  }

  public function getSubmitForm($info)
  {
    global $domainUrl;
    $paymentInfo = $this->_paymentInfo;
    $returnUrl = str_replace('http://', 'https://', url('payment/callback/creditcard'));
    if (floatval($info['orderAmount']) > 600) {
    	$this->_paymentInfo->use3Dgate = 1;
    }
    $gatewayNo = $this->_paymentInfo->use3Dgate == 0 ? $paymentInfo->gatewayNo :$paymentInfo->gateway3DNo;
    $signKey = $this->_paymentInfo->use3Dgate == 0 ? $paymentInfo->signKey :$paymentInfo->signKey3D;
    $signInfo=hash("sha256", $paymentInfo->merNo . $gatewayNo . $info['orderNumber'] . $paymentInfo->currency . $info['orderAmount'] . $returnUrl . $signKey);
    $html = array(
    		'<form action="' . $paymentInfo->submitUrl . '" method="post" >',
    		'<input type="hidden" name="merNo" value="' . $paymentInfo->merNo . '" />',
    		'<input type="hidden" name="gatewayNo" value="' . $gatewayNo .'" />',
    		'<input type="hidden" name="orderNo" value="' . $info['orderNumber'] . '" />',
    		'<input type="hidden" name="orderCurrency" value="' . $paymentInfo->currency . '" />',
    		'<input type="hidden" name="orderAmount" value="' . $info['orderAmount'] . '" />',
    		'<input type="hidden" name="signInfo" value="' . $signInfo . '" />',
    		'<input type="hidden" name="returnUrl" value="' .$returnUrl . '" />',
    		'<input type="hidden" name="firstName" value="'. $info["orderInfo"]->delivery_first_name . '" />',
    		'<input type="hidden" name="lastName" value="'. $info["orderInfo"]->delivery_last_name . '" />',
    		'<input type="hidden" name="email" value="'. $info["orderInfo"]->delivery_email . '" />',
    		'<input type="hidden" name="phone" value="'. $info["orderInfo"]->delivery_mobile . '" />',
    		'<input type="hidden" name="paymentMethod" value="Credit Card" />',
    		'<input type="hidden" name="country" value="'. $info["orderInfo"]->delivery_country . '" />',
    		'<input type="hidden" name="city" value="'. $info["orderInfo"]->delivery_city . '" />',
    		'<input type="hidden" name="address" value="'. $info["orderInfo"]->delivery_address . '" />',
    		'<input type="hidden" name="zip" value="'. $info["orderInfo"]->delivery_postcode . '" />',
		'<input type="hidden" name="isMobile" value="1" />',
    		'<input style="border:none" type="submit" class="pay_button" value="Pay Now"/>',
    		'</form>',);
    return implode(PHP_EOL, $html);
  }

  private function checkPost()
  {
    return false;
  }

  public function callback($orderNumber=null)
  {
  	$post = $_POST ? $_POST : $_GET;
  	if (empty($post)) {
  		return false;
  	}
  	if ($_POST) {
  		$result = array(
  				'verified' => false,
  				'paidAmount' => $post['orderAmount'],
  				'paymentId' => $post['tradeNo'],
  				'orderNumber' => $post['orderNo'],
  				'updateOrderStatus' => false,
  				'redirect' => '',
  				'message' => '',
  				'send_email' => true,
  				'payment_method' => 'creditcard',
	    );
	    $payment_status = $post['orderStatus'];
  	} else {
  		$result = array(
  				'verified' => false,
  				'paidAmount' => $post['orderAmount'],
  				'paymentId' => $post['tradeNo'],
  				'orderNumber' => $post['orderNo'],
  				'updateOrderStatus' => false,
  				'redirect' => '',
  				'message' => '',
  				'send_email' => true,
  				'payment_method' => 'creditcard',
	    );
	    $payment_status = $post['orderStatus'];
  	}
  	if (isset($post['isPush']) && ($post['isPush'] == 1 || $post['isPush'] == "1")) {
  		$result['send_email'] = true;
  	}
	/* 验证 */
	if ($payment_status == 1) {
		$result['verified'] = true;
		$result['updateOrderStatus'] = true;
		$result['message'] = 'You just completed your payment.';
	} else {
		$result['message'] = 'Sorry, your payment failed. No charges were made.<br /> reason:' . $post['orderInfo'];
	}
	return $result;
  }

  public function serverCallback()
  {
  	//var_dump($_POST);
   // exit;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $this->checkPost()) {

      $result = array(
        'verified' => true,
        'paidAmount' => $_POST['mc_gross'],
        'paymentId' => $_POST['txn_id'],
        'orderNumber' => $_POST['invoice'],
        'updateOrderStatus' => true,
        'redirect' => '',
        'message' => '',
      );
    } else {
      $result = array(
        'verified' => false,
      );
    }
    return $result;
  }
}
