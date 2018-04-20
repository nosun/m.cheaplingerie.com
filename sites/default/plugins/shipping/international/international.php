<?php

/**
 * UPS 插件
 */

class International extends Shipping_Abstract
{
    /*------------------------------------------------------ */
    //-- PUBLIC ATTRIBUTEs
    /*------------------------------------------------------ */

    /**
     * 配置信息
     */
    var $_configure_des= array(
      'calculateway' => '费用计算方式',
      'choices' => array('weight', 'num', 'fixed'),
      'default' => 'weight',
      'weight' => array(
          'wayname' => '按重量算',
          'base_fee' => '500克以内费用',
          'step_fee' => '续重每500克费用',
          'step2_fee'=> '续重达到21千克后每500克的费用',
          'free_money' => '免费额度',
      ),
      'num' => array(
          'wayname' => '按数量算',
          'item_fee' => '每个商品费用',
          'free_num' => '免费数量',
      ),
      'fixed' => array(
      	  'wayname' => '固定费用',
      	  'fixed_fee' => '运费',
      	  'free_money' => '免费额度',
      ),
      'btn' => '保存',
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
      if ($this->_configure['calculateway'] == 'num'){
        if ($this->_configure['free_num'] > 0 && $goods_number >= $this->_configure['free_num']) {
          return 0;
        }
        $fee = $goods_number * (isset($this->_configure['item_fee']) ? $this->_configure['item_fee'] : 0 );
      } else if ($this->_configure['calculateway'] == 'fixed'){
        if ($this->_configure['free_money'] > 0 && $goods_amount >= $this->_configure['free_money']) {
          return 0;
        }
        $fee = $this->_configure[fixed_fee];
      }
      else {
        $fee = isset($this->_configure['base_fee']) ? $this->_configure['base_fee'] : 0 ;
        if ($this->_configure['free_money'] > 0 && $goods_amount >= $this->_configure['free_money']) {
          return 0;
        }
        if (!$goods_weight) {
          return 0;
        }
        $goods_weight = $goods_weight * 1000;
        if($goods_weight > 500 && $goods_weight < 21000)
        {
          $fee += (ceil($goods_weight / 500) - 1) * $this->_configure['step_fee'];
        }else if($goods_weight >= 21000){
          $weight_step2 = $goods_weight - 21000;
          $fee += (ceil(21000 / 500) - 1) * $this->_configure['step_fee'];
          $fee += ceil($weight_step2/500)* $this->_configure['step_fee2'];
        }
      }
      return $fee;
    }
}
