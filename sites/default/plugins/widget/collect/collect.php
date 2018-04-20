<?php
class Collect extends Widget_Abstract
{
  var $_instance;
  
  public function urls()
  {
    return array(
      'getlist',
      'del',
      'add'
    );
  }

  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $setInfo = Bl_Config::get('widget.collect.settings', '1');
			$instance->view->render('../plugins/widget/collect/settings.phtml', array(
        'setInfo' => $setInfo,
      ));
  }

  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
		$setInfo = isset($_POST['on']) ? $_POST['on'] : '0';
    Bl_Config::set('widget.collect.settings',$setInfo);
    Bl_Config::save();
    setMessage('设置成功');
    gotoUrl('admin/site/widgetedit/collect');
  }

  public function install()
  {
    global $db;
    $sql = "CREATE TABLE IF NOT EXISTS `widget_collect`( `rec_id` BIGINT(12) NOT NULL AUTO_INCREMENT ,  `user_id` BIGINT(12) DEFAULT '0' ,    `goods_id` BIGINT(12) DEFAULT '0' ,   `timestamp` BIGINT(8) ,     PRIMARY KEY (`rec_id`)  );";
    $db->exec($sql);
    $setInfo = "1";
    Bl_Config::set('widget.collect.settings',$setInfo);
    Bl_Config::save();
  }

  public function uninstall(){}

  public function _getlist($instance, $page = 1, $pageRows = 5)
  {
    global $db,$user;
    $db->limitPage($pageRows, $page);
    $db->where('user_id', $user->uid);
    $db->orderby('timestamp DESC');
    $result = $db->get('widget_collect');
    $tempList = $result->allWithKey('rec_id');
    $goodsList = array();
    if($tempList && is_array($tempList)){
    	$pinstance = Product_Model::getInstance();
    	foreach($tempList as $k => $v){
    		$goodsList[$k]['goods'] = $pinstance->getProductInfo($v->goods_id);
    		$goodsList[$k]['timestamp'] = $v->timestamp;
    	}
    }
    $db->select('COUNT(0)');
    $db->where('user_id', $user->uid);
    $result = $db->get('widget_collect');
    $ListCount = $result->one();
	  if(hasFunction('page')){
	  		 callFunction('page', $instance);
	  }
    $instance->view->render('collectlist.phtml', array(
      'goodsList' => $goodsList,
      'goodsCount' => $ListCount,
      'pagination' => callFunction('pagination', 'widget/collect/getlist/%d/' . $pageRows, $ListCount, $pageRows, $page),
    ));
  }
  
  public function _add($instance, $goods_id = null)
  {
   $setInfo = Bl_Config::get('widget.collect.settings', '0');
   if(!$setInfo){
   		echo '-3';
      exit;
   }

  	global $db,$user;
  	$userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      echo '-1';
      exit;
    }
    if(empty($goods_id)){
    	echo '-2';
    	exit;
    }
    if($this->checkexist($goods_id)){
    	echo '2';
    	exit;
    }
  	$set = array(
		        'user_id' => $user->uid,
		        'goods_id' => $goods_id,
          	'timestamp' => TIMESTAMP,
	        );
	 $db->insert('widget_collect', $set);
	 echo $db->affected() ? '1' : '0';
	 exit;
  }
  
  private function checkexist($goods_id = null)
  {
  	global $db,$user;
  	$db->select('count(0)');
  	$db->where('user_id',$user->uid);
  	$db->where('goods_id',$goods_id);
  	$result = $db->get('widget_collect');
  	return $result->one();
  }
  public function _del($instance, $rec_id = null)
  {
		global $db,$user;
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
      exit;
    }
    if(hasFunction('page')){
  		 callFunction('page', $instance);
  	}

    $db->delete('widget_collect', array('rec_id' => $rec_id, 'user_id' => $user->uid));
    gotoUrl('widget/collect/getlist/');
  }
}