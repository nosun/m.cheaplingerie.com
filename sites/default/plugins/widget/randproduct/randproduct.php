<?php
class Randproduct extends Widget_Abstract
{
  private $_instance;

  public function urls()
  {
    return array(
    );
  }

  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $this->_instance = $instance;
    gotoUrl('admin/site/widgetedit/randproduct/index');
  }


  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    $this->_instance = $instance;
    $this->randProductDoExecute();
  }

  public function install()
  {
    $this->createRandproductTable();
  }

//FUNC-----------------------------------------------------------------------------------------

  public function index(Bl_Controller $instance, $widgetInfo)
  {
  	if ($instance->isPost()) {
  		$op_num = abs(intval($_POST['op_num']));
  		$todo = $_POST['todo'];
			$this->dbUpDownProduct($op_num, $todo) ? setMessage('操作完成') : setMessage('操作数不能过大(超过10000)或为0', 'error');
  	  gotoUrl('admin/site/widgetedit/randproduct/index');
  	} 
    $instance->view->render('../plugins/widget/randproduct/tIndex.phtml');
  }

  public function auto(Bl_Controller $instance, $widgetInfo)
  {
  	global $db;
    $settingName = 'product.rand';
    if ($instance->isPost()) {
      $post = $_POST;
      Bl_Config::set($settingName, $post['rand']);
      Bl_Config::save();
      if ($this->savetxt($post['rand'])) {
        setMessage('设置成功');
      }
      gotourl('admin/site/widgetedit/randproduct/auto');
    } else {
      $setting = Bl_Config::get($settingName, array());
      $instance->view->render('../plugins/widget/randproduct/auto.phtml', array(
          'setting' => isset($setting) ? $setting : array()
      ));
    }
  }
  
//DB-----------------------------------------------------------------------------------------

  private function savetxt($setting)
  {
    $filepath = DOCROOT . '/rand.auto.setting.txt';
    $hostname = strtolower($_SERVER['HTTP_HOST']);
    if (!file_exists($filepath)) {
      file_put_contents($filepath, '');
    }
    if (!is_writable($filepath)) {
      setMessage('rand.auto.setting.txt 文件不可写', 'error');
      return false;
    }
    if($setting['status'] == 1) {
      $file = fopen($filepath, "r");
      while(! feof($file)) {
        $line = fgets($file);
        if ($line == $hostname . PHP_EOL) {
          $exit = true;
        }
      }
      fclose($file);
      $file = fopen($filepath, "a");
      if (!$exit) {
        fputs($file, $hostname . PHP_EOL);
      }
    } else {
      $f1 = fopen($filepath,'r');
      $tmp = tempnam(DOCROOT, 'rand.auto.bak.txt');
      $f2=fopen($tmp,'w');
      while(!feof($f1)){
        $line = fgets($f1);
        if ($line != $hostname . PHP_EOL){ 
          fputs($f2,$line);
        } else {
          $exit = true;
        }
      }
      fclose($f1);
      fclose($f2);
      if ($exit) {
        unlink($filepath);
        rename($tmp, $filepath); 
      } else {
        unlink($tmp);
      }
    }
    return true;
  }
  
  private function dbUpDownProduct($op_num, $todo)
  {
  	global $db;
  	set_time_limit(0);

  	if ($op_num < 1 || $op_num > 10000) return false;
  	$sql = 'SELECT pid FROM products WHERE status=0 ORDER BY RAND() LIMIT ' . $op_num;
  	$result = $db->query($sql);
  	$pidList = $result->column('pid');
  	if (!$pidList || count($pidList) < 1) return 1;
  	unset($result);

		$status = $todo == 'up' ? 1 : 0;
  	foreach ($pidList as $key => $pid) {
  		$db->update('products', array('status' => $status), array('pid' => $pid));
  	}

  	$this->dbInsertRandProduct($op_num, $todo);
  }


  private function dbInsertRandProduct($op_num, $todo)
  {
  	global $db;
  	$set = array(
  		'action' => $todo,
	  	'op_num' => $op_num,
	  	'op_time' => time(),
  	);
  	$db->insert('widget_randproduct', $set);
  }


  private function createRandproductTable()
  {
  	global $db;
  	$sql = 'CREATE TABLE IF NOT EXISTS `widget_randproduct` (
						  `rpid` int(11) NOT NULL AUTO_INCREMENT,
						  `action` varchar(32) NOT NULL,
						  `op_num` int(11) NOT NULL,
						  `op_time` int(11) NOT NULL,
						  PRIMARY KEY (`rpid`)
						) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';

  	$db->exec($sql);
  }

}

