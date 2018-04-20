<?php
abstract class Payment_Abstract
{
  /**
   * 支付方式信息
   * @var object
   */
  protected $_paymentInfo;

  /**
   * 构造函数
   */
  public function __construct()
  {
    $paymentInstance = Payment_Model::getInstance();
    $this->_paymentInfo = $paymentInstance->getPaymentInfo($this->getPaymentName());
  }

  /**
   * 获取支付方式表示
   * @return string
   */
  abstract public function getPaymentName();

  /**
   * 获取配置
   * @return array
   */
  abstract public function getSettingFields();

  /**
   * 获取提交表单
   * @param mixed $set
   * @return string
   */
  abstract public function getSubmitForm($info);

  /**
   * 安装
   */
  public function install()
  {
    $payment = $this->getPaymentName();
    $paymentInstance = Payment_Model::getInstance();
    $paymentInfo = $paymentInstance->getPaymentInfo($payment);
    $fields = $this->getSettingFields();
    $set = array();
    foreach ($fields as $key => $value) {
      if (!isset($paymentInfo->{$key})) {
        if (is_array($value) && isset($value['default'])) {
          $set[$key] = $value['default'];
        } else {
          $set[$key] = '';
        }
      }
    }
    $paymentInstance->editPayment($payment, $set);
  }

  /**
   * 卸载
   */
  public function uninstall() {}
}

interface Payment_Interface
{
  /**
   * 页面回调
   */
  public function callback($orderNumber=null);
}

interface Payment_Server_Interface
{
  /**
   * 服务器回调
   */
  public function serverCallback();
}
