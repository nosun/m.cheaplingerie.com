<?php
class Paypal extends Payment_Abstract implements Payment_Interface, Payment_Server_Interface
{
  public function getPaymentName()
  {
    return 'paypal';
  }

  public function getSettingFields()
  {
    return array(
      'paypal_account' => '商户帐号',
      'paypal_currency' => array(
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
      'type' => array(
        'name' => '支付类型',
        'type' => 'select',
        'options' => array(
          '1' => '本站支付',
          '2' => '跳转支付',
        ),
        'default' => '1',
      ),
      'skipform' => '跳转支付地址',
    );
  }

  public function getSubmitForm($info)
  {
    global $domainUrl;
    $paymentInfo = $this->_paymentInfo;
    $no_note = Bl_Config::get('paypal.no_note', 1);
    $submiturl = ($paymentInfo->type == 1 ? 'https://www.paypal.com/cgi-bin/webscr' : $paymentInfo->skipform);
    $html = array(
      '<form action="' . $submiturl . '" method="post">',
// TODO the follow line just for testing, uncomment the above line instead the follow one.
//      '<form action="https://www.sandbox.paypal.com/cgi-bin/webscr" method="post" target="_blank">',
      '<input type="hidden" name="cmd" value="_xclick"/>',
      '<input type="hidden" name="business" value="' . $paymentInfo->paypal_account . '"/>',
      '<input type="hidden" name="item_name" value="Order: ' . $info['orderNumber'] . '"/>',
    	'<input type="hidden" name="item_number" value="' . $info['orderNumber'] . '"/>',
      '<input type="hidden" name="amount" value="' . $info['orderAmount'] . '">',
      '<input type="hidden" name="currency_code" value="' . $paymentInfo->paypal_currency . '"/>',
      '<input type="hidden" name="return" value="' . url('payment/callback/paypal') . '"/>',
      '<input type="hidden" name="invoice" value="' . $info['orderNumber'] . '"/>',
      '<input type="hidden" name="charset" value="utf-8"/>',
      '<input type="hidden" name="no_shipping" value="1"/>',
      '<input type="hidden" name="no_note" value="' . $no_note . '"/>',
      '<input type="hidden" name="notify_url" value="' . url('payment/notice/paypal') . '"/>',
// TODO http://113.108.143.212:8080 just for testing inside our office
//      '<input type="hidden" name="notify_url" value="' . ('http://113.108.143.212:8080' . url('payment/notice/paypal')) . '">',
      '<input type="hidden" name="rm" value="2">',
      '<input type="hidden" name="cancel_return" value="' . url('payment/fail/paypal') . '"/>',
      '<input style="border:none" type="submit" class="pay_button" value="' . t('Pay Now') . '">',
      '</form>',
    );
    return implode(PHP_EOL, $html);
  }

  private function checkPost()
  {
    $fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);
// TODO the follow line just for testing, uncomment the above line instead the follow one.
//    $fp = fsockopen('www.sandbox.paypal.com', 80, $errno, $errstr, 30);
    if ($fp) {
      $paymentInfo = $this->_paymentInfo;
      $paymentStatus = $_POST['payment_status'];

      $req = 'cmd=_notify-validate';
      foreach ($_POST as $key => $value) {
        $value = urlencode(stripslashes($value));
        $req .= "&$key=$value";
      }
      $header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
      $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
      $header .= "Content-Length: " . strlen($req) ."\r\n\r\n";
      fputs($fp, $header . $req);
      while (!feof($fp)) {
        $res = fgets($fp, 1024);
        if (strcmp($res, 'VERIFIED') == 0 && $_POST['receiver_email'] == $paymentInfo->paypal_account &&
          $_POST['mc_currency'] == $paymentInfo->paypal_currency &&
          ($paymentStatus == 'Completed' || $paymentStatus == 'Pending')) {
            fclose($fp);
            return true;
        }
      }
    }
    fclose($fp);
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
	      'paidAmount' => $post['mc_gross'],
	      'paymentId' => $post['txn_id'],
	      'orderNumber' => $post['invoice'],
	      'updateOrderStatus' => false,
	      'redirect' => '',
	      'message' => '',
  		  'payment_method' => 'paypal',
	    );
	    $payment_status = $post['payment_status'];
  	} else {
  		$result = array(
	      'verified' => false,
	      'paidAmount' => $post['amt'],
	      'paymentId' => $post['tx'],
	      'orderNumber' => $post['item_number'],
	      'updateOrderStatus' => false,
	      'redirect' => '',
	      'message' => '',
  		  'payment_method' => 'paypal',
	    );
	    $payment_status = $post['st'];
  	}

		/* 验证 */
			if ($payment_status == 'Completed' || $payment_status == 'Pending') {
				$result['verified'] = true;
				$result['updateOrderStatus'] = true;
				$result['message'] = 'You just completed your payment.';
				$_SESSION['checkout_orderInfo']->status_payment = 1;
			} else {
				$result['message'] = 'Failure to pay';
				$_SESSION['checkout_orderInfo']->status_payment = 0;
			}
		//var_dump($result);
		//exit;
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
