<?php
class Admin_Login_Controller extends Bl_Controller
{
  public function indexAction()
  {
    $userInstance = User_Model::getInstance();
    if ($userInstance->logged()) {
      gotoUrl(access('administrator page') ? 'admin' : '');
    }
    if ($this->isPost()) {
      if (!isset($_POST['username']) || strlen(trim($_POST['username'])) < 3) {
        setMessage(t('Username must be at least 3 characters long.'), 'error');
      } else if (!$uid = $userInstance->validate(trim($_POST['username']), $_POST['password'])) {
        setMessage('Username or Password is invalid.', 'error');
      } else {
        if ($uid == 1) {//is administrator
          $perm = true;
        } else {
          $roles = $userInstance->getUserRoles($uid);
          $roles[] = User_Model::ROLE_AUTHENTICATED_USER;//往数组的最后插一个元素
          $perm = false;
          foreach ($roles as $rid) {
            if (access('administrator page', 'and', $userInstance->getRolePermissions($rid))) {
              $perm = true;
              break;
            }
          }
        }
        if (!$perm) {
          setMessage(t('Access denied.'), 'error');
        } else {
          $userInstance->setLogin($uid);
          $userInfo = $userInstance->getUserInfo($uid);
          log::save('user', $userInfo->name . ' login.');
          gotoUrl('admin');
        }
      }
      gotoUrl('admin/login');
    } else {
      $this->view->render('admin/default/login.phtml');
    }
  }
}
