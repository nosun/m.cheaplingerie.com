<?php

/**
 * FeDex 插件
 */

class Ems extends Shipping_Abstract
{
    /*------------------------------------------------------ */
    //-- PUBLIC ATTRIBUTEs
    /*------------------------------------------------------ */

    /**
     * 配置信息
     */
    var $_configure_des= array(
      'calculateway' => '费用计算方式',
      'choices' => array('weight', 'num'),
      'default' => 'weight',
      'weight' => array(
          'wayname' => '按重量算',
          'base_fee' => '500克以内费用',
          'step_fee' => '5000克以内续重每500克费用',
          'step2_fee' => '5001克以上续重500克费用',
          'free_money' => '免费额度',
      ),
      'num' => array(
          'wayname' => '按数量算',
          'item_fee' => '每个商品费用',
          'free_num' => '免费数量',
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
      } else {
        $fee = isset($this->_configure['base_fee']) ? $this->_configure['base_fee'] : 0 ;
        if ($this->_configure['free_money'] > 0 && $goods_amount >= $this->_configure['free_money']) {
          return 0;
        }
        if (!$goods_weight) {
          return $fee;
        }
        if ($goods_weight > 0.5){
          $good_weight1 = $goods_weight > 5 ? 5 : $goods_weight;
          $fee += ceil($good_weight1 / 0.5 - 1) * $this->_configure['step_fee'];
          if ($goods_weight > 5) {
            $fee += ceil(($goods_weight - 5) / 0.5) * $this->_configure['step2_fee'];
          }
        }
      }
      return $fee;
    }
}
