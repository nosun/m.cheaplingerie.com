<?php
class Guestbook_Controller extends Bl_Controller
{
  private $_commentInstance;
  
  public function init()
  {
    $this->_commentInstance = Comment_Model::getInstance();
  }
  
  public function addAction()
  {
    if ($this->isPost()) {
      $post = $_POST;
      if (isset($post['data']) && $post['data']) {
        $post['data'] = serialize($post['data']);
      }
      if ($this->_commentInstance->insertWebsiteMessage($post)) {
        setMessage(t('Thank you very much for your feedback'));
      } else {
        setMessage(t('Sorry, the operation failed'));
      }
      $reffer_url = $_SERVER["HTTP_REFERER"];
      if(isset($post['referer']) && $post['referer']) {
        gotoUrl($post['referer']); 
      } elseif (isset($reffer_url) && $reffer_url) {
        header("Location: ".$reffer_url); 
      } else {
        gotoUrl('guestbook/add');
      }
    }
    $this->view->render('addguestbook.phtml');
  }
  
}