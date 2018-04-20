<?php
class Shipping_Model extends Bl_Model
{
  /**
   * @return Payment_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  public function shippingList($front = false)
  {
    $shippingList = Bl_Plugin::getList('shipping');
    foreach($shippingList as $k => $v) {
      $array = Bl_Config::get('shipping.' . $k, array('status' => false, 'visible' => true));
      $v->status = isset($array['status']) ? $array['status'] : false;
      $v->visible = isset($array['visible']) ? $array['visible'] : true;
      $v->weight = isset($array['weight']) ? $array['weight'] : 0;
      if ($front) {
        if (isset($array['name_f'])) {
          $v->name = $array['name_f'];
        }
        if (isset($array['descripe_f'])) {
          $v->description = $array['descripe_f'];
        }
      }
    }
    return $this->sortingByWeight($shippingList);
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
}