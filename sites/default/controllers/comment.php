<?php
class Comment_Controller extends Bl_Controller
{
  private $_commentModel;
  
  public function init()
  {
    $this->_commentModel = Comment_Model::getInstance();
  }
  
  public function insertAction()
  {
    global $user;
    if ($this->isPost()) {
      $post = $_POST;
      if (!$post['nickname']){
        exit('Name can not be empty!');
      }

      if ($post['comment']) {
        exit('Review content can not be empty');
      }
      $post['comment'] = trim($post['comment']);
      $post['comment'] = strip_tags($post['comment']);
      if (!$post['subject']) {
        $post['subject'] = substr($post['comment'], 0, 50);
        $pos = strpos($post['subject'], "\n");
        if($pos !== false){
        	$post['subject'] = strpos($post['subject'], $needle);
        }
      }
      $post['comment'] = preg_replace("/\r\n(\r\n)+/", "</p><p>", $post['comment']);
      $post['comment'] = preg_replace("/\n(\n)+/", "</p><p>", $post['comment']);
      
      $post['comment'] = str_ireplace("\r\n", "</br>", $post['comment']);
      $post['comment'] = str_ireplace("\n", "</br>", $post['comment']);
      
      $uid = isset($user->uid) ? $user->uid : 0;
      $nickname = (isset($_POST['nickname']) && $_POST['nickname']) ? $_POST['nickname'] : (isset($user->nickname) ? $user->nickname : $user->name);
      $productInstance = Product_Model::getInstance();
      if ($productInstance->getProductInfo($post['pid'])) {
        $status = Bl_Config::get('isNeedAudit', 1) == 1 ? 0 : 1;
        $cid = $this->_commentModel->insertComment($uid, $post['subject'], $post['comment'], $nickname, $status);
        if ($cid) {
          cache::remove('product-' . $post['pid']);
          $this->_commentModel->insertProductComments($post['pid'],$cid);
          if (isset($post['rating'])) {
            $grade = $post['rating'];
            widgetCallFunction('fivestars', 'setstars', $post['pid'], $cid, $grade);
            cache::remove('productStart-' . $post['pid']);
          }
        }
        
        
        $stmpSetting = Bl_Config::get('stmp', 0);
        if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
        	$mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
        	$email[] = $_POST['delivery_email'];
        	$siteInfo = Bl_Config::get('siteInfo', array());
        	if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
        		$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
        	}
            $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
                setMessage('Pay Success, please check your mail');
              } else {
                setMessage('send mail error', 'error');
              }
        } else {
        	setMessage('Mail server information is not configured properly, please check', 'error');
        }
        
        
        
        $reffer_url = $_SERVER["HTTP_REFERER"];
        if(isset($post['referer']) && $post['referer']) {
          gotoUrl($productInstance->getProductInfo($post['pid'])->path_alias);
        } elseif (isset($reffer_url) && $reffer_url) {
          gotoUrl($productInstance->getProductInfo($post['pid'])->path_alias);
        } else {
          gotoUrl($productInstance->getProductInfo($post['pid'])->path_alias);
        }
      } else {
        //TODO
        exit('No goods');
      }
    }else{
    	gotoUrl($productInstance->getProductInfo($post['pid'])->path_alias);
    }
  }


  public function ajaxUpdateUserAttitudeAction(){
    
  }


  public function addReplyAction()
  {
    global $user;
    if ($this->isPost()) {
      $post = $_POST;
      if (!$post['uid']){
        exit('You need to first login to add reply!');
      }
      if (!$post['review_comment']) {
        exit('Review comment should not be empty!');
      }
      if(strpos($post['review_comment'], '<a ') !== false || strpos($post['review_comment'], 'href') !== false){
        $arr = array();
        $arr['ok'] = false;
      }else{
        $uid = isset($user->uid) ? $user->uid : 0;
        $nickname = (isset($user->nickname) && $user->nickname != '') ? $user->nickname : $user->name;
        $email = $user->email;
        $status = Bl_Config::get('isNeedAudit', 1) == 1 ? 0 : 1;

      	$post['review_comment'] = preg_replace("/\r\n(\r\n)+/", "</p><p>", $post['review_comment']);
      	$post['review_comment'] = preg_replace("/\n(\n)+/", "</p><p>", $post['review_comment']);
      	$post['review_comment'] = str_ireplace("\r\n", "</br>", $post['review_comment']);
      	$post['review_comment'] = str_ireplace("\n", "</br>", $post['review_comment']);
        
        $cid = $this->_commentModel->insertComment($uid, $email, $post['review_comment'], array(), $nickname, $status, '', $post['replyid']);
        $arr = $this->_commentModel->getReplyCommentInfo($cid);
        $arr->time = date('M d, Y', $arr->timestamp);
        $arr->ok = true;
      }
      echo json_encode($arr);
    }
  }
  
  public function ajaxgetcommentAction(){
  	if($this->isPost()){
  		$post = $_POST;
  		$pid = $post['pid'];
  		$page = $post['offsetpage'];
  		
  		$productInstance = Product_Model::getInstance();
  		$product = $productInstance->getProductInfo($pid);
  		
  		$commentInstance = Comment_Model::getInstance();
  		$comments = $commentInstance->getCommentsListByProductId($product->pid,array(), $page, 5);
//   		$comments = $commentInstance->addOrderitemPropertyToComments($comments, $product->pid);
        $commentcount = $commentInstance->getCommentsCountByProductId($product->pid);
  		
  		
  		$this->view->render('contents/p_comments.phtml',array(
  			'product' => $product,
  			'comments' => $comments,
  			'pagination' => callFunction('combo_pagination', '', ceil($commentcount/5), $page),
  		));
  	}
  }
}