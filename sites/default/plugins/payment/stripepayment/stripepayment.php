<?php

class Stripepayment extends Payment_Abstract implements Payment_Interface, Payment_Server_Interface
{
  public function getPaymentName()
  {
    return 'stripepayment';
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
  	$dir = dirname(__FILE__);
  	$path = $dir . "/config.php";
  	require_once $path;
  	if (!isset($stripe))
  	{
  		return "";
  	}
    $paymentInfo = $this->_paymentInfo;
    $data_amount = floatval($info['orderAmount']);
    $themeName = Bl_Config::get('template', 'default');
	$logo = url('templates/' . $themeName . '/images/stripe_logo.png');
    
	/*
	$html = array('<form action="' . url("payment/callback/stripepayment/") . $info['orderNumber'] . '/" method="post">',
	    '<script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"',
	    'data-key="' . $stripe["publishable_key"] . '"',
	    'data-name="Payout"',
	    'data-description="Thank you for you order"',
    	'data-amount="' . $data_amount . '"',
	    'data-image="' . $logo . '">',
	  	'</script>',
		'</form>',
    );
	$html = array(
	    '<form>',
            '<h2>Payment details</h2>',
            '<ul>',
            	'<li>',
            		'<ul class="cards">',
                        '<li class="visa">Visa</li>',
                        '<li class="visa_electron">Visa Electron</li>',
                        '<li class="mastercard">MasterCard</li>',
                        '<li class="maestro">Maestro</li>',
                        '<li class="discover">Discover</li>',
            		'</ul>',
            	'</li>',
                '<li>',
                    '<label for="card_number">Card number</label>',
                    '<input type="text" name="card_number" id="card_number">',
                '</li>',
                '<li class="vertical">',
                    '<ul>',
                        '<li>',
                        	'<label for="expiry_date">Expiry date <small>mm/yy</small></label>',
                        	'<input type="text" name="expiry_date" id="expiry_date" maxlength="5">',
                        '</li>',
                        '<li>',
                        	'<label for="cvv">CVV</label>',
                        	'<input type="text" name="cvv" id="cvv" maxlength="3">',
                        '</li>',
                    '</ul>',
                '</li>',
                '<li class="vertical maestro" style="display: none; opacity: 0;">',
                	'<ul>',
                		'<li>',
                            '<label for="issue_date">Issue date <small>mm/yy</small></label>',
                            '<input type="text" name="issue_date" id="issue_date" maxlength="5">',
                		'</li>',
                		'<li>',
                            '<span class="or">or</span>',
                            '<label for="issue_number">Issue number</label>',
                            '<input type="text" name="issue_number" id="issue_number" maxlength="2">',
                        '</li>',
                	'</ul>',
                '</li>',
                '<li>',
                	'<label for="name_on_card">Name on card</label>',
                	'<input type="text" name="name_on_card" id="name_on_card">',
                '</li>',
                '<li>',
                	'<input type="submit" value="Pay $33.75" id="pay_submit" style="background:#45b1e8; cursor: pointer;" >',
                '</li>',
            '</ul>',
		'</form>',
	);*/
	/*
	$html = array(
	    '<form action="" method="POST" id="payment-form" style="width: 287px;margin: 0 0 32px; background-color: #f8f8f8; border: 5px solid #f5f5f5;font-size: 16px;>',
        '  <span class="payment-errors"></span>',
        '  <div class="form-row" style="height: 34px;margin-bottom: 20px;">',
        '    <label>',
        '      <span>Card Number</span>',
        '      <input type="text" size="20" data-stripe="number"/>',
        '    </label>',
        '  </div>',
        '  <div class="form-row">',
        '    <label>',
        '      <span>CVC</span>',
        '      <input type="text" size="4" data-stripe="cvc"/>',
        '    </label>',
        '  </div>',
        '  <div class="form-row">',
        '    <label>',
        '      <span>Expiration (MM/YYYY)</span>',
        '      <input type="text" size="2" data-stripe="exp-month"/>',
        '    </label>',
        '    <span> / </span>',
        '    <input type="text" size="4" data-stripe="exp-year"/>',
        '  </div>',
        '  <input type="submit" value="Submit Payment" />',
        '</form>',
	);
	$html = array(
		'<iframe src="' . url('templates/' . $themeName . '/credit_card.html') . '" style="border: 0" width="450" height="450" frameborder="0" scrolling="no" />',
	    '</iframe>'
	);*/
	
	$html = '<script type="text/javascript" src="https://js.stripe.com/v2/"></script>' .
			'<script language="javascript" src="' . url('scripts/jquery-1.7.2.min.js') . '" type="text/javascript"></script>' .
			'<script language="javascript" src="' . url("scripts/jquery.payment.js") . '" type="text/javascript"></script>' . 
	        '<script>' . 'formAction="' . url("payment/callback/stripepayment/") . $info['orderNumber'] . '/"' . ';payAmount="' . c($data_amount) . '";</script>';

	$html .= file_get_contents($dir . '/credit_card.html');
	return $html;
    //return implode(PHP_EOL, $html);
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
  	$result = array(
  		'verified' => false,
  		'updateOrderStatus' => false);
  	if ($_POST)
  	{
  		log::save('debug', 'Call Back Post', $_POST);
  		$token = $_POST['stripeToken'];
  		
  		//$orderInfo = $_SESSION["checkout_orderInfo"];
  		$orderInfo = Order_Model::getInstance()->getOrederInfoByNumber($orderNumber);
  		if (!isset($orderInfo) || !isset($orderInfo->pay_amount))
  		{
  			$result["message"] = "Failed to pay, the order may be lost, please try to order again.";
  		}
  		else 
  		{
  			$payAmount = $orderInfo->pay_amount * 100;
  			$result['paidAmount'] = $orderInfo->pay_amount;
  			$result['orderNumber'] = $orderInfo->number;
  			$cur_currency = isset($_SESSION['currency'])? strtolower($_SESSION['currency']) :'usd';
	  		try 
	  		{
	  			require_once dirname(__File__) . '/config.php';
	  			$charge = Stripe_Charge::create(array(
	  				"amount" => $payAmount,
	  				"currency" => $cur_currency,
	  				"card" => $token,
	  				"description" => $_POST["name"],
	  				"metadata" => array("order_number" => $orderInfo->number),
	  			));
	  			$result['verified'] = true;
				$result['updateOrderStatus'] = true;
				$result['message'] = 'Successful payment';
	  		}catch(Stripe_CardError $e) 
	  		{
	  			$result['verified'] = false;
				$result['updateOrderStatus'] = false;
				$result['message'] = $e;
			}
  		}
  	}
  	else 
  	{
  		$result['message'] = 'Failed payment';
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
