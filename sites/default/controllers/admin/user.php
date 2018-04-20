<?php
class Admin_User_Controller extends Bl_Controller
{
  private $_userInstance;

  public static function __permissions()
  {
    return array(
      'manage user',
      'manage rank',
      'manage permissions',
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_userInstance = User_Model::getInstance();
  }

  public function indexAction()
  {
    $this->listAction();
  }
  public function firstListAction($key)
  {
    if ($key == 'all') {
      foreach ($_SESSION['listuser'] as $key1 => $dl) {
        unset($_SESSION['listuser'][$key1]);
      }
    } else {
      unset($_SESSION['listuser'][$key]);
    }
    gotoUrl('admin/user/list');
  }

  public function listAction($page = 1)
  {
    if (!access('manage user')) {
      goto403('Access Denied.');
    }
    $firstfilter = array('uid >' => 1);
    if ($this->isPost()) {
      $filter = $_POST;
      foreach ($_POST as $key=>$dl) {
        $_SESSION['listuser'][$key] = $dl;
      }
    }else{
      $filter = $firstfilter;
    }
    if(isset($_SESSION['listuser'])){
        $filter = $_SESSION['listuser'];
    }

    $usersCount = $this->_userInstance->getUsersCount($filter);
    $usersList = $this->_userInstance->getUsersList($filter, $page, 20);
    $ranksList = $this->_userInstance->getRanksList();
    $this->view->addCss(url('styles/themes/base/jquery.ui.datepicker.css'));
    $this->view->render('admin/user/userslist.phtml', array(
      'usersList' => $usersList,
      'ranksList' => $ranksList,
      'selectHtml' =>  array_diff($filter, $firstfilter)? '<b>'.t('Select Term').'（<a href="'.url('admin/user/firstList/all').'">'.t('Clear Away').'</a>）</b>' : null,
      'pagination' => pagination('admin/user/list/%d', $usersCount, 20, $page),
    ));
  }

  public function exportAction()
  {
     require LIBPATH . '/PHPExcel.php';
      $objPHPExcel = new PHPExcel();
      set_time_limit(2000);
      $userFileds = $this->_userInstance->getUserFieldsName();
      $i = 0;
      $userFiledAlias = array('编号','用户名','会员状态','会员等级','姓名','性别','生日','邮箱','电话','手机号','地区','国家','省州','市区','邮编','积分','登录次数','注册时间','注册IP','上次登录时间','上次登录IP');
      foreach ($userFileds as $key => $dl) {
        if (!in_array($dl, array('passwd', 'cid', 'pid', 'data','login_timestamp','login_ip')) ) {
  $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, 1, $dl);
          $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, 1, $userFiledAlias[$key]);
          $i++;
        }
      }

      if(isset($_SESSION['listuser'])){
        $filter = $_SESSION['listuser'];
      }else{
        $filter = array('uid >' => 1);
      }

      $ranksList = $this->_userInstance->getRanksList();
      $usersCount = $this->_userInstance->getUsersCount($filter);
      $pageCount = ceil($usersCount/20);
      $j = 1;
      $siteInstance = Site_Model::getInstance();
      $countries = $siteInstance->getCountries();

      for ($page = 1; $page <= $pageCount; $page++){
        $usersList = $this->_userInstance->getUsersList($filter, $page, 20);
        foreach($usersList as $key => $dl){
          $i = 0;$j++;
            foreach ($dl as $key2 => $dd){
              switch ($key2){
                case country : {
                  $dd = (!$dd && $dl->cid > 0) ? $countries[$dl->cid] : null;
                  break;
                }
                case province : {
                  if( !$dd && $dl->cid > 0 && $dl->pid > 0){
                    $provinces = $siteInstance->getProvinces($dl->cid);
                    $dd = $provinces[$dl->pid] ? $provinces[$dl->pid] : null;
                  }
                   break;
                }
                case status : {
                  $dd = $dd ? '激活' : '禁止';
                  break;
                }
                case gender : {
                   $dd = $dd == User_Model::GENDER_MALE ? t('Male') : ($dd == User_Model::GENDER_FEMALE ? t('Female') : null );
                   break;
                }
                case rid : {
                  $dd = isset($ranksList[$dd]) ? plain($ranksList[$dd]->name) : null;
                  break;
                }
                case birthday : {
                  $dd = $dd ? plain(substr($dd, 0, 4) . '-' . intval(substr($dd, 4, 2)) . '-' . intval(substr($dd, 6, 2))) : null;
                  break;
                }
                case created : {
                  $dd = $dd ? date('Y-m-d H:i:s', $dd) : null;
                  break;
                }
                case updated : {
                  $dd = $dd ? date('Y-m-d H:i:s', $dd) : null;
                  break;
                }
              }

              if (!in_array($key2, array('passwd', 'cid', 'pid', 'data','login_timestamp','login_ip')) ) {
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, $j, $dd);
                $i++;
              }
            }
        }
      }

      $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
      header('Content-Type: application/vnd.ms-excel; charset=utf-8');
      header("Content-Disposition: attachment;filename=users-".date('Y-m-d').".xls");
      header('Cache-Control: max-age=0');
      $objWriter->save('php://output');
  }

  public function editAction($uid = null)
  {
    if (!access('manage user')) {
      goto403('Access Denied.');
    }
    if (isset($uid)) {
      if ($uid <= 1) {
        gotoUrl('admin/user/list');
      }
      if (!$userInfo = $this->_userInstance->getUserInfo($uid)) {
        goto404('User ID <em>' . $uid . '</em> not found.');
      }
      $userInfo->roles = $this->_userInstance->getUserRoles($uid);
    }
    $isnew = !isset($uid);
    $roles = $this->_userInstance->getRolesList(true);
    if ($this->isPost()) {
      if (!isset($_POST['roles'])) {
        $_POST['roles'] = array();
      }
      $post = (object)$_POST;
      if (isset($uid)) {
        $post->name = $userInfo->name;
      }
      if (!isset($post->gender)) {
        $post->gender = User_Model::GENDER_NONE;
      }
      if (isset($post->rank)) {
        $post->rid = $post->rank;
      }
      $userInfo = $post;
      $oldUserName = '';
      if (isset($uid)) {
        $checkUser = $this->_userInstance->getUserInfo($uid);
        $oldUserName = $checkUser->name;
      }
      if (!isset($userInfo->name) || !$this->_userInstance->checkNameIsValid(trim($userInfo->name))) {
        setMessage('用户名错误', 'error');
      } else if ( $this->_userInstance->getUserInfoByName(trim($userInfo->name)) && $userInfo->name != $oldUserName ) {
        setMessage('用户名已存在', 'error');
      } else if (($isnew || $userInfo->password !== '') && strlen($userInfo->password) < 5) {
        setMessage('密码格式不正确', 'error');
      } else if ($userInfo->password !== '' && $userInfo->password !== $userInfo->password_confirm) {
        setMessage('确认密码不匹配', 'error');
      } else {
        $rid = User_Model::RANK_MEMBER;
        if (isset($_POST['rank'])) {
          $ranksList = $this->_userInstance->getRanksList();
          if (isset($ranksList[$_POST['rank']])) {
            $rid = $_POST['rank'];
          }
        }
        $set = array(
          'status' => isset($userInfo->status) ? 1 : 0,
          'nickname' => trim($userInfo->nickname),
          'rid' => $rid,
          'gender' => $userInfo->gender == User_Model::GENDER_MALE || $userInfo->gender == User_Model::GENDER_FEMALE ? $userInfo->gender : User_Model::GENDER_NONE,
          'email' => trim($userInfo->email),
          'phone' => trim($userInfo->phone),
          'mobile' => trim($userInfo->mobile),
          'area' => trim($userInfo->area),
          'postcode' => trim($userInfo->postcode),
          'points' => isset($userInfo->points) ? trim($userInfo->points) : null,
        );
        if ($isnew) {
          $set['name'] = trim($userInfo->name);
        }
        if ($userInfo->password != '') {
          $set['passwd'] = $userInfo->password;
        }
        if (trim($userInfo->birthday) != '' && preg_match('/^(\w{4})[-\/](\w{1,2})[-\/](\w{1,2})$/', trim($userInfo->birthday), $matches)) {
          $set['birthday'] = $matches[1] . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        } else {
          $set['birthday'] = '';
        }
        $rolesSet = array();
        if (isset($_POST['roles'])) {
          foreach ($_POST['roles'] as $role) {
            if (isset($roles[$role])) {
              $rolesSet[] = $role;
            }
          }
        }
        if (isset($uid)) {
          $this->_userInstance->updateUser($uid, $set, $rolesSet);
          setMessage('设置成功');
          gotoUrl('admin/user/edit/'.$uid);
        } else {
          if ($this->_userInstance->getUserInfoByName($set['name'])) {
            setMessage('用户名已存在', 'error');
          } else {
            $this->_userInstance->insertUser($set, $rolesSet);
            gotoUrl('admin/user/list');
          }
        }
      }
    } else if (!isset($uid)) {
      $userInfo = null;
    }
    $this->view->render('admin/user/userinfo.phtml', array(
      'isnew' => $isnew,
      'account' => $userInfo,
      'ranksList' => $this->_userInstance->getRanksList(),
      'rolesList' => $roles,
    ));
  }

  public function deleteAction($uid)
  {
    if (!access('manage user')) {
      goto403('Access Denied.');
    }
    if ($userInfo = $this->_userInstance->getUserInfo($uid)) {
      $this->_userInstance->deleteUser($uid);
    }
    gotoUrl('admin/user/list');
  }

  public function ranksAction()
  {
    if (!access('manage rank')) {
      goto403('Access Denied.');
    }
    $ranksList = $this->_userInstance->getRanksList();
    $this->view->render('admin/user/rankslist.phtml', array(
      'ranks' => $ranksList,
    ));
  }

  public function rankeditAction($rid = null)
  {
    if (!access('manage rank')) {
      goto403('Access Denied.');
    }
    if (isset($rid) && !$rankInfo = $this->_userInstance->getRankInfo($rid)) {
      goto404('Rank ID <em>' . $rid . '</em> not found.');
    }
    $isnew = !isset($rid);
    if ($this->isPost()) {
      $rankInfo = (object)$_POST;
      if (!isset($rankInfo->name) || trim($rankInfo->name) == '') {
        setMessage('会员等级名称错误', 'error');
      } else {
        $existRank = $this->_userInstance->getRankInfoByName(trim($rankInfo->name));
        if ($existRank && (isset($rid) && $rid != $existRank->rid)) {
          setMessage('会员等级名称重复', 'error');
        } else if (!is_numeric($rankInfo->discount) || $rankInfo->discount < 0 || $rankInfo->discount > 100) {
          setMessage('折扣范围错误', 'error');
        } else {
          $set = array(
            'name' => trim($rankInfo->name),
            'discount' => intval($rankInfo->discount),
          );
          if (isset($rid)) {
            $this->_userInstance->updateRank($rid, $set);
            gotoUrl('admin/user/ranks');
          } else {
            $this->_userInstance->insertRank($set);
            gotoUrl('admin/user/ranks');
          }
        }
      }
    } else if (!isset($rid)) {
      $rankInfo = null;
    }
    $this->view->render('admin/user/rankinfo.phtml', array(
      'isnew' => $isnew,
      'rank' => $rankInfo,
    ));
  }

  public function rankdeleteAction($rid)
  {
    if (!access('manage rank')) {
      goto403('Access Denied.');
    }
    if ($rankInfo = $this->_userInstance->getRankInfo($rid)) {
      $this->_userInstance->deleteRank($rid);
    }
    gotoUrl('admin/user/ranks');
  }

  public function rolesAction()
  {
    if (!access('manage permissions')) {
      goto403('Access Denied.');
    }
    $rolesList = $this->_userInstance->getRolesList();
    $this->view->render('admin/user/roleslist.phtml', array(
      'roles' => $rolesList,
    ));
  }

  public function roleeditAction($rid = null)
  {
    if (!access('manage permissions')) {
      goto403('Access Denied.');
    }
    if (isset($rid) && !$roleInfo = $this->_userInstance->getRoleInfo($rid)) {
      goto404('Role ID <em>' . $rid . '</em> not found.');
    }
    $isnew = !isset($rid);
    if ($this->isPost()) {
      $roleInfo = (object)$_POST;
      if (!isset($roleInfo->name) || trim($roleInfo->name) == '') {
        setMessage('角色名称错误', 'error');
      } else {
        $existRole = $this->_userInstance->getRoleInfoByName(trim($roleInfo->name));
        if ($existRole && (isset($rid) && $rid != $existRole->rid)) {
          setMessage('角色名称重复', 'error');
        } else {
          $set = array(
            'name' => trim($roleInfo->name),
          );
          if (isset($rid)) {
            $this->_userInstance->updateRole($rid, $set);
            gotoUrl('admin/user/roles');
          } else {
            $this->_userInstance->insertRole($set);
            gotoUrl('admin/user/roles');
          }
        }
      }
    } else if (!isset($rid)) {
      $roleInfo = null;
    }
    $this->view->render('admin/user/roleinfo.phtml', array(
      'isnew' => $isnew,
      'role' => $roleInfo,
    ));
  }

  public function roledeleteAction($rid)
  {
    if (!access('manage permissions')) {
      goto403('Access Denied.');
    }
    if ($roleInfo = $this->_userInstance->getRoleInfo($rid)) {
      $this->_userInstance->deleteRole($rid);
    }
    gotoUrl('admin/user/roles');
  }

  public function rolepermissionAction($rid)
  {
    if (!access('manage permissions')) {
      goto403('Access Denied.');
    }
    if (isset($rid) && !$roleInfo = $this->_userInstance->getRoleInfo($rid)) {
      goto404('Role ID <em>' . $rid . '</em> not found.');
    }
    if ($this->isPost()) {
      if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
        $set = $_POST['permissions'];
      } else {
        $set = array();
      }
      $this->_userInstance->updateRolePermissions($rid, $set);
      setMessage('权限设置成功');
      gotoUrl('admin/user/rolepermission/' . $rid);
    }
    $permissions = $this->_userInstance->getPermissions();
    $roleInfo->permissions = $this->_userInstance->getRolePermissions($rid);

    $this->view->render('admin/user/rolepermissions.phtml', array(
      'role' => $roleInfo,
      'permissions' => $permissions,
    ));
  }

  public function permissionsAction()
  {
    if (!access('manage permissions')) {
      goto403('Access Denied.');
    }
    $roles = $this->_userInstance->getRolesList();
    if ($this->isPost()) {
      foreach ($roles as $rid => $role) {
        if (isset($_POST['permissions']) && isset($_POST['permissions'][$rid]) && is_array($_POST['permissions'][$rid])) {
          $set = $_POST['permissions'][$rid];
        } else {
          $set = array();
        }
        $this->_userInstance->updateRolePermissions($rid, $set);
      }
      setMessage('权限设置成功');
      gotoUrl('admin/user/permissions/');
    }
    $permissions = $this->_userInstance->getPermissions();
    foreach ($roles as $rid => &$role) {
      $role->permissions = $this->_userInstance->getRolePermissions($rid);
    }

    $this->view->render('admin/user/permissions.phtml', array(
      'roles' => $roles,
      'permissions' => $permissions,
    ));
  }
}
