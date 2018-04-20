<?php
class Admin_Comment_Controller extends Bl_Controller
{
  const PAGEROWS = 15;

  private $_commentModel;

  public static function __permissions()
  {
    return array(
      'manage comment',
      'manage guestbook',
    );
  }

  public function init()
  {
    $this->_commentModel = Comment_Model::getInstance();
  }

  public function getListAction($page = 1, $status = null, $type = 'product')
  {
    if (!access('manage comment')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $pageRows = self::PAGEROWS;
    if ($type == 'product') {
      $commentList = $this->_commentModel->getCommentsList($page, $pageRows, $status);
    }
    $count = $this->_commentModel->getCountCommentsList($status);
    $this->view->render('admin/comment/list.phtml', array(
      'commentList' => $commentList,
      'pagehtml' => pagination('admin/comment/getList/%d' . (isset($status) ? ('/' . $status) : ''), $count, $pageRows, $page),
      'page' => $page,
      'pageRows' => $pageRows,
      'count' => $count,
      'status' => $status,
    ));

  }

  public function getInfoAction($cid, $status=1, $page=1)
  {
    if (!access('manage comment')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if (!$this->_commentModel->CheckCommentCidExist($cid)) {
      setMessage('This comment can not found!');
      gotoUrl('admin/comment/getList');
    }
    $commentInfo = $this->_commentModel->getCommentInfo($cid);
    $this->view->render('admin/comment/info.phtml', array(
      'commentInfo' => $commentInfo,
      'page' => $page,
      'status' => $status,
    ));


  }

  public function insertAction($pid = 0)
  {
    if (!access('manage comment')) {
      goto403('Access Denied.');
    }
  	global $user;
  	if (!$pid) {
  		setMessage('请指定您要添加评论的商品。', 'error');
  		gotoUrl('admin/product/list/');
  	}
  	if ($this->isPost()) {
  		$uid = $user->uid;
  		$Product_Model = Product_Model::getInstance();
  		if (!$Product_Model->getProductInfo($pid)) {
  			setMessage('商品不存在！', 'error');
  			gotoUrl('admin/product/list/');
  		}
  		if ($_POST['subject']=='') {
        setMessage('请填写评论标题', 'error');
        gotoUrl('admin/comment/insert/'.$pid);
      }
      $cid = $this->_commentModel->insertComment($uid,$_POST['subject'], $_POST['comment'], $_POST['nickname'], $_POST['status'], $_POST['ip']);
      if ($cid) {
        $this->_commentModel->insertProductComments($pid, $cid);
      }
      setMessage('成功添加评论。');
      gotoUrl('admin/comment/getList');
  	} else {
  		$this->view->render('admin/comment/addComment.phtml', array('pid' => $pid));
  	}
  }

  /**
   * 后台回复评论
   * @param INT $cid
   */
  public function replayAction($cid)
  {
    if (!access('manage comment')) {
      goto403('Access Denied.');
    }
    global $user;
    if (!$cid) {
      setMessage('请指定您要回复的评论。', 'error');
      gotoBack();
    }
    $commentInfo = $this->_commentModel->getCommentInfo($cid);
    if (!$commentInfo) {
      setMessage('评论不存在！', 'error');
      gotoBack();
    }
    if ($this->isPost()) {
      $uid = $user->uid;
      if ($_POST['subject']=='') {
        setMessage('请填写评论标题', 'error');
        gotoUrl('admin/comment/insert/'.$pid);
      }
      if ($commentInfo->replayComment) {
        $this->_commentModel->updateComment($commentInfo->replayComment->cid, $uid, $_POST['subject'], $_POST['comment'], $user->nickname, 1, ipAddress());
      } else {
        $cid = $this->_commentModel->insertComment($uid, $_POST['subject'], $_POST['comment'], $user->nickname, 1, ipAddress(), $cid);
        setMessage('成功回复评论。');
      }
      gotoUrl('admin/comment/getList');
    } else {
      $this->view->render('admin/comment/replayComment.phtml', array('cid' => $cid, 'commentInfo' => $commentInfo, 'replay' => $commentInfo->replayComment));
    }
  }

  public function dealAction($cid,$status,$page,$source_status)
  {
    if (!access('manage comment')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if (count(explode(',',$cid)) == 1 && !$this->_commentModel->CheckCommentCidExist($cid)) {
      setMessage('This comment can not found!');
      gotoUrl('admin/comment/getList');
    } else {
     $result = $this->_commentModel->updateCommentStatus($cid, $status);
      if (!$result) {
        setMessage('评论处理失败', 'error');
      }
      gotoUrl('admin/comment/getList/' . $page . '/' . $source_status);
    }

  }

  public function deleteAction($cid, $page, $status)
  {
    if (!access('manage comment')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if (count(explode(',',$cid)) == 1 && !$this->_commentModel->CheckCommentCidExist($cid)) {
      setMessage('This comment can not found!');
      gotoUrl('admin/comment/getList');
    } else {
      $cids = explode(',', $cid);
      foreach($cids as $k => $v) {
        $result = $this->_commentModel->deleteComment($v);
        if ($result) {
          $result = $this->_commentModel->deleteProductComments($cid);
        }
      }
      gotoUrl('admin/comment/getList/' . $page . '/' . $status );
    }
  }

  public function postAction()
  {
    if (!access('manage comment')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if ($this->isPost()) {
      if (!empty($_POST['checkItem'])){
       $cids = implode(',',$_POST['checkItem']);
     }
     if ($_POST['isall'] == 1) {
        if ($_POST['action'] == 'auditProductComment') {
         $this->_commentModel->updateCommentCondition($_POST['status'],1);
       } else if ($_POST['action'] == 'delProductComment') {
         $this->_commentModel->deleteProductCommentsCondition($_POST['status']);
         $this->_commentModel->deleteCommentCondition($_POST['status']);
       } else if ($_POST['action'] == 'readProductComment') {
         $this->_commentModel->updateCommentCondition($_POST['status'],2);
       }
       gotoUrl('admin/comment/getList/1/'.$_POST['status']);
     } else {
       if (empty($cids)) {
         gotoUrl('admin/comment/getList/'.$_POST['page'].'/'.$_POST['status']);
       }
       if ($_POST['action'] == 'auditProductComment') {
       	 $this->dealAction($cids, 1, $_POST['page'],$_POST['status']);
       } else if ($_POST['action'] == 'delProductComment') {
         $this->deleteAction($cids, $_POST['page'],$_POST['status']);
       } else if ($_POST['action'] == 'getListProductComment') {
          gotoUrl('admin/comment/getList/1/'.$_POST['status']);
       } else if ($_POST['action'] == 'readProductComment') {
         $this->_commentModel->updateCommentStatus($cids,2);
       }
       gotoUrl('admin/comment/getList/1/'.$_POST['status']);
     }
    }
  }

  public function settingAction()
  {
    if (!access('setting')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if ($this->isPost()) {
      $isOpen = isset($_POST['isOpen']) ? $_POST['isOpen'] : "";
      $isNeedAudit = isset($_POST['isNeedAudit']) ? $_POST['isNeedAudit'] : "";
      Bl_Config::set('isOpen',$isOpen);
      Bl_Config::set('isNeedAudit',$isNeedAudit);
      Bl_Config::save();
      $this->view->render('admin/comment/setting.phtml', array(
        'isOpen' => $isOpen,
        'isNeedAudit' => $isNeedAudit,
      ));
    } else {
      $isOpen = Bl_Config::get('isOpen');
      $isNeedAudit = Bl_Config::get('isNeedAudit');
      $this->view->render('admin/comment/setting.phtml', array(
        'isOpen' => $isOpen,
        'isNeedAudit' => $isNeedAudit,
      ));
    }
  }

  public function messageListAction($page = 1, $status = null)
  {
    if (!access('manage guestbook')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if ($this->isPost()) {
      if (!empty($_POST['checkItem'])){
        $gbids = implode(',',$_POST['checkItem']);
      }
      if ($_POST['action'] == 'showMessage') {
        $post = array(); $post['status'] = 1;
        $this->_commentModel->updateWebsiteMessageList($gbids, $post);
      } else if ($_POST['action'] == 'hideMessage') {
        $post = array(); $post['status'] = 2;
        $this->_commentModel->updateWebsiteMessageList($gbids, $post);
      } else if ($_POST['action'] == 'delMessage') {
        $this->_commentModel->deleteWebsiteMessageList($gbids);
      }
      gotoUrl('admin/comment/messageList/1/'.$_POST['status']);
    }
    if ($status == 'all') {
      $status = null;
    }
    $pageRows = self::PAGEROWS;
    $messageList = $this->_commentModel->getWebsiteMessageList($page, $pageRows, $status);
    $count = $this->_commentModel->getWebsiteMessageCount($status);
    $this->view->render('admin/comment/messageList.phtml', array(
      'messageList' => $messageList,
      'pagehtml' => pagination('admin/comment/messageList/%d' . (isset($status) ? ('/' . $status) : ''), $count, $pageRows, $page),
      'page' => $page,
      'pageRows' => $pageRows,
      'count' => $count,
      'status' => $status,
    ));
  }

  public function getMessageInfoAction($gpid = 0)
  {
    if (!access('manage guestbook')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $messageInfo = $this->_commentModel->getWebsiteMessageInfo($gpid);
    $this->view->render('admin/comment/messageInfo.phtml', array(
      'messageInfo' => $messageInfo,
    ));
  }

  public function updateMessageStatusAction($gpid = 0, $status = 2)
  {
    $post = array('status' => $status );
    $this->_commentModel->updateWebsiteMessage($gpid, $post);
    gotoBack();
  }

  public function deleteMessageAction($gpid = 0)
  {
    if (!access('manage guestbook')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }

    $this->_commentModel->deleteWebsiteMessage($gpid);
    gotoUrl('admin/comment/messageList/1/');
  }

}
