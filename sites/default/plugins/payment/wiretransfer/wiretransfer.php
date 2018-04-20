<?php
class Wiretransfer extends Payment_Abstract
{
  public function getPaymentName()
  {
    return 'wiretransfer';
  }

  public function getSettingFields()
  {
    return array(
    'gotourl' => '跳转地址',
    );
  }

  public function getSubmitForm($info)
  {
    $paymentInfo = $this->_paymentInfo;
  	if(isset($paymentInfo->gotourl) && $paymentInfo->gotourl){
	    $html = array(
	      '<form action="'. $paymentInfo->gotourl . '" method="get" target="_blank">',
	      '<input type="submit" class="pay_button" value="' . t("Pay Now") . '">',
	      '</form>',
	    );
	    return implode(PHP_EOL, $html);
  	}else{
  		return null;
  	}   
  }
}
