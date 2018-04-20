<?php

/**
 * UPS 插件
 */

class Overnightexpress extends Shipping_Abstract
{
    /*------------------------------------------------------ */
    //-- PUBLIC ATTRIBUTEs
    /*------------------------------------------------------ */

    /**
     * 配置信息
     */
    var $_configure_des= array(
      'calculateway' => 'Shipping Fee Calculate Method',
      'choices' => array('fixed'),
      'default' => 'fixed',
      'fixed' => array(
      	  'wayname' => 'Fixed Fee',
      	  'fixed_fee' => 'Shipping Fee',
      	  'free_money' => 'Free Money',
      ),
      'btn' => 'Save',
    );

    var $_configure;
    /*------------------------------------------------------ */
    //-- PUBLIC METHODs
    /*------------------------------------------------------ */

    /**
     * 构造函数
     * @return null
     */
    public function initialize($configure = null)
    {
      if ($configure) {
        $this->_configure = $configure;
      }
    }

    /**
     * 计算订单的配送费用的函数
     *
     * @param   float   $goods_weight   商品重量
     * @param   float   $goods_amount   商品金额
     * @param   float   $goods_number   商品件数
     * @return  decimal
     */
    public function calculate($goods_weight, $goods_amount, $goods_number)
    {
    	if ($this->_configure['calculateway'] == 'fixed'){
        	if ($this->_configure['free_money'] > 0 && $goods_amount >= $this->_configure['free_money']) {
          	return 0;
        }
        $fee = $this->_configure[fixed_fee];
      }
      return $fee;
    }
}
