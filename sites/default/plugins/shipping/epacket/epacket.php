<?php
class EPacket extends Shipping_Abstract
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
          'base_fee' => '每单固定费用',
    	  'weight_fee_unit'=>'每千克费用',
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
	 * 98元/KG+13元 /票+ 8元
	 * 17/kg + 3.5
	 */
	public function calculate($goods_weight, $goods_amount, $goods_number){
		if ($this->_configure['calculateway'] == 'num'){
			if ($this->_configure['free_num'] > 0 && $goods_number >= $this->_configure['free_num']) {
				return 0;
			}
			$fee = $goods_number * (isset($this->_configure['item_fee']) ? $this->_configure['item_fee'] : 0 );
		}
		else if ($this->_configure['calculateway'] == 'fixed'){
		    if ($this->_configure['free_money'] > 0 && $goods_amount >= $this->_configure['free_money']) {
          		return 0;
        	}
        	$fee = $this->_configure[fixed_fee];
      	}else{
      	    if ($this->_configure['free_money'] > 0 && $goods_amount >= $this->_configure['free_money']) {
          		return 0;
        	}
			$fee = $goods_weight * $this->_configure['weight_fee_unit'] + $this->_configure['base_fee'];
		}
		return $fee;
	}
}
