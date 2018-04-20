<?php
class Test_Controller extends Bl_Controller
{
	private $_testInstance;
	
	public function jsonpAction($jsname){
		$params = $_GET;
		$start = $params['start'];
		$end = $params['end'];
		$callback = $params['callback'];
		
		$backdata = array();
		srand(microtime(true) * 1000);
		$backdata['data'] = rand($start, $end);
		
		
// 		echo json_encode($backdata);
		$this->view->render('jsonptest.js',array(
			'callback' => $callback,
			'result' => json_encode($backdata),
		));
	}
	
	public function testAction(){
		$info = "23333weqweqwewqeqweqw";
		$this->view->render("hzztest.phtml", array(
			'info' => $info,
		));
	}
	
	public function ajaxgetdataAction(){
    	echo rand(1, 49);  
	}
	
}
?>

