<?php
abstract class Shipping_Abstract
{
 /**
  * 生成表单
  */
  public function config($configure = null)
  {
  	if ($configure) {
      $configure_des = $this->_configure_des[$configure['calculateway']];
    } else {
      $configure_des = $this->_configure_des[$this->_configure_des['default']];
    }
    $str = '<table>';
    $str .= '<tr><td width="30%">区域名称</td><td><input name="areaname" value="'.(isset($configure['areaname']) ? $configure['areaname'] : null ).'" class="txt non-empty" title="区域名称不能为空" > <span style="color:red;">*</span></td></tr>';
    $str .= '<tr><td width="30%">'.$this->_configure_des['calculateway'].'</td>';
    $str .= '<td>';
    foreach ($this->_configure_des['choices'] as $choice){
    	$str .= '<input type="radio" ';
    	if (!isset($configure['calculateway']) || $configure['calculateway'] == $choice) {
    		$str .= 'checked = "checked" ';
    	}
    	 $str .= 'name="calculateway" value="'.$choice.'" class="inputsubmit">'.$this->_configure_des[$choice]['wayname'];
    	 $str .= '&nbsp;&nbsp;';
    }
    /*
    if (!isset($configure['calculateway']) || $configure['calculateway'] == 'weight') {
      $str .= 'checked = "checked" ';
    }
    $str .= 'name="calculateway" value="weight" class="inputsubmit">'.$this->_configure_des['weight']['wayname']
    .'&nbsp;&nbsp;<input type="radio"  ';
    if (isset($configure['calculateway']) && $configure['calculateway'] == 'num') {
      $str .= 'checked = "checked"';
    }
    $str .= 'name="calculateway" value="num" class="inputsubmit">'.$this->_configure_des['num']['wayname'];
    */
    foreach($configure_des as $key => $v) {
      if ($key != 'wayname') {
        $str .= '<tr><td width="30%">'.$configure_des[$key].'</td><td><input class="txt" name="'.$key.'"';
	      if ($configure) {
	        $str .= 'value="'.(isset($configure[$key]) ? $configure[$key] : null ).'"';
	       }
	       $str .= '></td></tr>';
	    }
    }
    $str .= '</table>';
    return $str;
  }
  
  abstract public function calculate($goods_weight, $goods_amount, $goods_number);
}