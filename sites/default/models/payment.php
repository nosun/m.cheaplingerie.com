<?php
class Payment_Model extends Bl_Model
{
  /**
   * @return Payment_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取支付方式列表
   * @param boolean 是否重新读取
   * @return array
   */
  public function getPaymentsList($reset = false)
  {
    static $list = null;
    if (!isset($list) || $reset) {
      $list = Bl_Plugin::getList('payment', $reset);
      foreach ($list as $pk => &$payment) {
        $settings = Bl_Config::get('payment.' . $pk, array(
          'status' => false,
          'visible' => true,
        ));
        if (is_array($settings)) {
          foreach ($settings as $key => $value) {
            $payment->{$key} = $value;
          }
        } else {
          $payment->settings = $settings;
        }
      }
    }
    return $this->sortingByWeight($list);
  }
  
 
  public function sortingByWeight($arr) {
    $newarr = array();
    $result = array();
    $key = array();
    if (is_array($arr)) {
      foreach ($arr as $k => $v) {
        $v->weight = isset($v->weight) ? $v->weight : 0;
        $newarr[$v->weight][$k] = $v;
        $key[$v->weight][$k] = $k;
      }
      krsort($newarr);
      foreach ($newarr as $k => $v) {
        foreach($v as $k2 => $v2) {
          $result[$k2] = $v2;
        }
      }
    }
    return $result;
  }

  /**
   * 获取支付方式信息
   * @param string $payment 支付方式标识
   */
  public function getPaymentInfo($payment)
  {
    $payments = $this->getPaymentsList();
    return isset($payments[$payment]) ? $payments[$payment] : false;
  }

  /**
   * 获取支付方式实例
   * @param object $payment 支付方式标识/对象
   * @return Payment_Abstract
   * @return Payment_Interface
   * @return Payment_Server_Interface
   */
  public function getPaymentInstance($payment)
  {
    return Bl_Plugin::getInstance('payment', $payment);
  }

  /**
   * 修改支付方式设置
   * @param string $payment 支付方式标识
   * @param array $post 表单数组
   * @return array
   */
  public function editPayment($payment, $post)
  {
    $settings = Bl_Config::get('payment.' . $payment, array(
      'status' => false,
      'visible' => true,
    ));
    foreach ($post as $key => $value) {
      if (is_null($value)) {
        unset($settings[$key]);
      } else {
        $settings[$key] = $value;
      }
    }
    Bl_Config::set('payment.' . $payment, $settings);
    Bl_Config::save();
    return $settings;
  }

  /**
   * 获取订单支付信息
   * @param object $order
   * @return array
   */
  public function getOrderPaymentInfo($order)
  {
    $items = array(
      'goods' => array(),
      'fees' => array(),
    );
  	if (isset($order->items) && is_array($order->items)) {
	  	foreach ($order->items as $k => $v) {
	  	  $items['goods'][$k] = array();
	  		$items['goods'][$k]['name'] = $v->name;
	  		$items['goods'][$k]['pay_price'] = $v->pay_price;
	  		$items['goods'][$k]['number'] = $v->number;
	  		$items['goods'][$k]['total_amount'] = $v->total_amount;
	  	}
  	}
  	if (isset($order->fees) && is_array($order->fees)) {
	  	foreach ($order->fees as $k => $v) {
	  	  $items['fees'][$k] = array();
	      $items['fees'][$k]['fee_name'] = $v->fee_name;
	      $items['fees'][$k]['fee_value'] = $v->fee_value;
	  	}
  	}
    return array(
      'orderId' => $order->oid,
      'orderNumber' => $order->number,
      'orderAmount' => $order->pay_amount,
      'items' => $items,
      'orderInfo' => $order
    );
  }
}
