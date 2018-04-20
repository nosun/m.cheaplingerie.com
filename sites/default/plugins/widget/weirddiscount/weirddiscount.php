<?php
class WeirdDiscount extends Widget_Abstract
{
  var $_instance;


  public function install()
  {
    global $db;
    $sql = 'CREATE TABLE IF NOT EXISTS `widget_weirddiscount`(`sid` CHAR(32) NOT NULL, `uid` INT(10) NOT NULL ,  `used`  INT(1) UNSIGNED NOT NULL DEFAULT "0" ,  PRIMARY KEY (`sid`)  );';
    $db->exec($sql);
    
    $discount = "1";
    Bl_Config::set('widget.weirddiscount.settings',$discount);
    Bl_Config::save();
  }

  public function uninstall()
  {
    global $db;
    $sql = 'DROP TABLE `widget_weirddiscount`;';
    $db->exec($sql);
  }

  //when edit this widget
  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    $this->_instance = $instance;
    $discount = $_POST['discount'];
    Bl_Config::set('widget.weirddiscount.settings',$discount);
    Bl_Config::save();
  }

  //after the widget edited( apply settings for perparing.
  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $this->_instance = $instance;
    $discount = Bl_Config::get('widget.weirddiscount.settings', '1');
	$instance->view->render('../plugins/widget/weirddiscount/settings.phtml', array(
        'discount' => $discount,
      ));
  }
  /*
  public function applyDiscount($oid){
  	$orderInstance = Order_Model::getInstance();
  	$orderInfo = $orderInstance->getOrderInfo($oid);
  	$orderInfo->
  	updateOrderFee
  }
  */
}