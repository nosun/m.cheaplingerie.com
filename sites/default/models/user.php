<?php
class User_Model extends Bl_Model
{
  const GENDER_NONE = 0;
  const GENDER_MALE = 1;
  const GENDER_FEMALE = 2;

  const RANK_MEMBER = 1;

  const ROLE_ANONYMOUS_USER = 1;
  const ROLE_AUTHENTICATED_USER = 2;

  const USER_TYPE_LOCAL = 0;
  const USER_TYPE_FACEBOOK = 1;
  const USER_TYPE_GOOGLE = 2;
  
  /**
   * @return User_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取用户数量
   * @param array $filter 过滤数组
   * @return int
   */
  public function getUsersCount($filter = array())
  {
    global $db;
   $filter = array(
        'name' => isset($filter['username']) ? trim($filter['username']) : null,
        'email' => isset($filter['email']) ? trim($filter['email']) : null,
        'nickname' => isset($filter['nickname']) ? trim($filter['nickname']) : null,
        'created >=' => isset($filter['startTime']) ? strtotime($filter['startTime']) : null,
        'created <=' => isset($filter['endTime']) && $filter['endTime'] ? strtotime($filter['endTime']) + 3600*24 : null,
        'rid' => isset($filter['rank']) ? $filter['rank'] : null,
        'status = ' => isset($filter['status']) ? $filter['status'] : 0,
        'uid >' =>1
    );

    foreach ($filter as $key => $value) {
      if ($value) {
        $db->where($key, $value);
      }
    }
    $db->select('COUNT(0)');
    $result = $db->get('users');
    return $result->one();
  }

  /**
   * 获取用户列表
   * @param array $filter 过滤数组
   * @param int $page 分页
   * @param int $pageRows 每分页行数
   * @return array
   */
  public function getUsersList($filter = array(), $page = null, $pageRows = 20)
  {
    global $db;

    $filter = array(
        'name' => isset($filter['username']) ? trim($filter['username']) : null,
        'email' => isset($filter['email']) ? trim($filter['email']) : null,
        'nickname' => isset($filter['nickname']) ? trim($filter['nickname']) : null,
        'created >=' => isset($filter['startTime']) ? strtotime($filter['startTime']) : null,
        'created <=' => isset($filter['endTime']) && $filter['endTime'] ? strtotime($filter['endTime']) + 3600*24 : null,
        'rid' => isset($filter['rank']) ? $filter['rank'] : null,
        'status = ' => isset($filter['status']) ? $filter['status'] : 0,
        'uid >' =>1
    );

    foreach ($filter as $key => $value) {
      if ($value) {
        $db->where($key, $value);
      }
    }
    $db->orderby('uid DESC');
    if (isset($page)) {
      $db->limitPage($pageRows, $page);
    }
    $db->where('uid > ', 0);
    $result = $db->get('users');
    return $result->allWithKey('uid');
  }

  /**
   * 获取用户信息
   * @param int $uid 用户ID
   * @return object
   */
  public function getUserInfo($uid)
  {
    global $db;
    if( !$uid )
    	return false;
    static $list = array();
    if (!isset($list[$uid])) {
      $cacheId = 'user-' . $uid;
      if ($cache = cache::get($cacheId)) {
        $userInfo = $cache->data;
      } else {
        $result = $db->query('SELECT * FROM users WHERE uid = ' . $db->escape($uid));
        $userInfo = $result->row();
        $userInfo->data = (isset($userInfo->data) && $userInfo->data) ? unserialize($userInfo->data) : array();
        cache::save($cacheId, $userInfo);
      }
      $list[$uid] = $userInfo;
    }
    return $list[$uid];
  }

  /**
   * 获取三方登录的用户信息
   * @email email地址
   * @id 第三方网站的id信息
   * @type 第三方网站的id 1:facebook 2:google+
   */
  public function getThirdPartyUserInfo($email, $id, $type)
  {
      global $db;
      if (empty($email) || empty($id) || empty($type))
      {
          return false;
      }
      $sql = sprintf("select * from users where email = '%s' and type = %d and thirdparty_id = '%s'", $db->escape($email), $type, $db->escape($id));
      $result = $db->query($sql);
      $userInfo = $result->row();
      if ($userInfo)
      {
          $cacheId = 'user-' . $userInfo->uid;
          if (!cache::get($cacheId))
          {
              cache::save($cacheId, $userInfo);
          }
          return $userInfo;
      }
      return false;
  }
  /**
   * 根据用户名获取用户信息(只适用于在本网站注册的用户)
   * @param string $name 用户名
   * @param int $type 用户的类型
   * @return object
   */
  public function getUserInfoByName($name)
  {
    global $db;
    static $list = array();
    if (!isset($list[$name])) {
      $sql = sprintf("select uid from users where name = '%s'", $db->escape($name));
      $result = $db->query($sql);
      $list[$name] = $result->one();
    }
    return $list[$name] ? $this->getUserInfo($list[$name]) : $list[$name];
  }

  /**
   * 检查用户名格式是否有效
   * @param string $name 用户名
   * @return boolean
   */
  public function checkNameIsValid($name)
  {
    return (boolean)(preg_match('/^\w{3,20}$/i', $name) || preg_match('/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[\.][a-z]{2,3}([\.][a-z]{2})?/i', $name));
  }
  
  /**
   * 检查注册邮箱是否存在(只适用于在本网站注册的用户)
   * @param string $email
   * @return boolean $uid
   */
  public function isValidEmail($email)
  {
  	global $db;
  	$sql = sprintf("select uid from users where email = '%s'", $db->escape($email));
  	$result = $db->query($sql);
    $uid = $result->one();
    return $uid;
  }

  /**
   * 产生用户散列密码
   * @param string $string 密码明文
   * @return string 散列密码
   */
  private function generatePassword($string)
  {
    return md5($string);
  }

  /**
   * 验证用户名密码, 只对在网站注册的用户验证(type=0)，不对第三方登录方式的用户进行验证
   * @param string $username 用户名
   * @param string $password 密码
   * @param boole  $is_email 是否为邮箱
   * @return int 用户ID
   */
  public function validate($name, $passwd, $is_email = FALSE)
  {
    global $db;
    $field_name = $is_email ? 'email' : 'name';
    $result = $db->query('SELECT uid FROM `users` WHERE `uid` > 0 AND `'. $db->escape($field_name) .'` = "' . $db->escape($name) .
      '" AND `passwd` = "' . $db->escape($this->generatePassword($passwd)) . '" AND `status` = 1');
    return $result->one();
  }

  /**
   * 检查用户是否已登录
   * @return boolean
   */
  public function logged()
  {
    global $user;
    return (boolean) $user->uid;
  }

  /**
   * 设置用户登录
   * @param int $uid 用户ID
   */
  public function setLogin($uid)
  {
    global $db, $user;
    $user->uid = $uid;
    $set = array(
      'login_count' => array(
        'escape' => false,
        'value' => 'login_count + 1',
      ),
      'login_timestamp' => TIMESTAMP,
      'login_ip' => ipAddress(),
    );
    //why this was not enabled?
    $db->update('users', $set, array('uid' => $uid));
    $db->update('cart_products', array('uid'=>$uid), array('sid' => $user->sid));
    
    $db->select('upid');
    $db->where('sid', $user->sid);
    $db->where('uid', 0);
    $result = $db->get('users_wish');
    $upids = $result->all();
    foreach ($upids as $upid){
    	$db->update('users_wish', array('uid'=>$uid), array('upid' => $upid->upid));
    }

    // 心愿单登陆后去重
    $db->select("*");
    $db->where('sid', $user->sid);
    $db->where('uid', $user->uid);
    $result = $db->get('users_wish');
    $list = $result->all();
    foreach($list as $k => $v){
    	$db->where('uid', $user->uid);
    	$db->where('pid', $v->pid);
    	$result = $db->get('users_wish');
    	$final_list = $result->row();
    	if(!($final_list->upid == $v->upid)){
    		$db->delete('users_wish', array('upid' => $v->upid));
    	}
    }
    // 去重完 
    
    cache::remove('user-' . $uid);
    $cartModel = Cart_Model::getInstance();
    $cartModel->mergeCart($user->sid);
    callFunction('login', $uid);
    widgetCallFunctionAll('login', $uid);
  }

  /**
   * 设置用户登出
   */
  public function setLogout()
  {
    global $user;
    $uid = $user->uid;
    if ($uid) {
      //per requirements, not delete the shopping cart information when a user logout.
      //$cartModel = Cart_Model::getInstance();
      //$cartModel->deleteCart();
      $user->uid = 0;
      session_destroy();
      callFunction('logout', $uid);
      widgetCallFunctionAll('logout', $uid);
    }
  }

  /**
   * 获取用户角色
   * @param int $uid 用户ID
   * @return array
   */
  public function getUserRoles($uid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$uid])) {
      $cacheId = 'user-roles-' . $uid;
      if ($cache = cache::get($cacheId)) {
        $roles = $cache->data;
      } else {
        $result = $db->query('SELECT rid FROM users_roles WHERE uid = ' . $db->escape($uid));
        $roles = $result->column();
        cache::save($cacheId, $roles);
      }
      $list[$uid] = $roles;
    }
    return $list[$uid];
  }

  /**
   * 新建用户角色
   * @param int $uid 用户ID
   * @param array $roles 角色列表
   */
  public function insertUserRoles($uid, $roles)
  {
    global $db;
    foreach ($roles as $rid) {
      $db->exec('INSERT INTO users_roles (uid, rid) VALUES (' . $db->escape($uid) . ', ' . $db->escape($rid) . ')');
    }
    cache::remove('user-roles-' . $uid);
  }

  /**
   * 修改用户角色
   * @param int $uid 用户ID
   * @param array $roles 角色列表
   */
  public function updateUserRoles($uid, $roles)
  {
    global $db;
    $this->deleteUserRoles($uid);
    $this->insertUserRoles($uid, $roles);
  }

  /**
   * 删除用户角色
   * @param int $uid 用户ID
   */
  public function deleteUserRoles($uid)
  {
    global $db;
    $db->exec('DELETE FROM users_roles WHERE uid = ' . $db->escape($uid));
    cache::remove('user-roles-' . $uid);
  }

  /**
   * 新建用户
   * @param array $post 表单数组
   * @return int
   */
  public function insertUser($post, $roles) 
  {
    global $db;
    if (isset($post['name']) && '' == $post['name']) {
      return false;
    }
    $set['passwd'] = $this->generatePassword($post['passwd']);
    $set['name'] = $post['name'];
    $set['created'] = TIMESTAMP;
    $set['created_ip'] = ipAddress();
    $set['updated'] = TIMESTAMP;
    $set['updated_ip'] = ipAddress();
    isset($post['gender']) ? $set['gender'] = $post['gender'] : 0;
    isset($post['cid']) ? $set['cid'] = $post['cid'] : 0;
    isset($post['pid']) ? $set['pid'] = $post['pid'] : 0;
    isset($post['country']) ? $set['country'] = $post['country'] : '';
    isset($post['province']) ? $set['province'] = $post['province'] : '';
    isset($post['city']) ? $set['city'] = $post['city'] : '';
    isset($post['nickname']) ? $set['nickname'] = $post['nickname'] : '';
    isset($post['birthday']) ? $set['birthday'] = $post['birthday'] : '';
    isset($post['email']) ? $set['email'] = $post['email'] : '';
    isset($post['phone']) ? $set['phone'] = $post['phone'] : '';
    isset($post['mobile']) ? $set['mobile'] = $post['mobile'] : '';
    isset($post['postcode']) ? $set['postcode'] = $post['postcode'] : '';
    isset($post['area']) ? $set['area'] = $post['area'] : '';
    isset($post['data']) ? ($set['data'] = is_array($post['data']) ? serialize($post['data']) : $post['data']) : '';
    isset($post['thirdparty_id']) ? $set['thirdparty_id'] = $post['thirdparty_id'] : null;
    $db->insert('users', $set);
    $uid = $db->lastInsertId();
    $this->insertUserRoles($uid, $roles);
    return $uid;
  }

  /**
   * 修改用户
   * @param int $uid 用户ID
   * @param array $post 表单数组
   * @return int
   */
  public function updateUser($uid, $post, $roles = null)
  {
    global $db;
    if (isset($post['name'])) {
      unset($post['name']);
    }
    if (isset($post['passwd'])) {
      $post['passwd'] = $this->generatePassword($post['passwd']);
    }
    $set = $post;
    isset($post['gender']) ? $set['gender'] = $post['gender'] : 0;
    isset($post['cid']) ? $set['cid'] = $post['cid'] : 0;
    isset($post['pid']) ? $set['pid'] = $post['pid'] : 0;
    isset($post['country']) ? $set['country'] = $post['country'] : '';
    isset($post['province']) ? $set['province'] = $post['province'] : '';
    isset($post['city']) ? $set['city'] = $post['city'] : '';
    isset($post['nickname']) ? $set['nickname'] = $post['nickname'] : '';
    isset($post['birthday']) ? $set['birthday'] = $post['birthday'] : '';
    isset($post['email']) ? $set['email'] = $post['email'] : '';
    isset($post['phone']) ? $set['phone'] = $post['phone'] : '';
    isset($post['mobile']) ? $set['mobile'] = $post['mobile'] : '';
    isset($post['postcode']) ? $set['postcode'] = $post['postcode'] : '';
    isset($post['area']) ? $set['area'] = $post['area'] : '';
    isset($post['data']) ? ($set['data'] = is_array($post['data']) ? serialize($post['data']) : $post['data']) : '';
    isset($post['rid']) ? $set['rid'] = $post['rid'] : 1;
    $set['updated'] = TIMESTAMP;
    $set['updated_ip'] = ipAddress();
    $db->update('users', $set, array('uid' => $uid));
    cache::remove('user-' . $uid);
    if (isset($roles)) {
      $this->updateUserRoles($uid, $roles);
    }
    return (boolean)$db->affected();
  }

  /**
   * 删除用户
   * @param int $uid 用户ID
   * @return boolean
   */
  public function deleteUser($uid)
  {
    global $db;
    $db->exec('DELETE FROM users WHERE uid = ' . $db->escape($uid));
    cache::remove('user-' . $uid);
    $affected = $db->affected();
    $this->deleteUserRoles($uid);
    return (boolean)$affected;
  }

  /**
   * 获取用户表字段名
   * return object
   */
  public function getUserFieldsName()
  {
    global $db;
    $result = mysql_query('SELECT * FROM users limit 1');
    $i = 0;
    while ($meta = mysql_fetch_field($result)) {
      if ($meta->name != 'passwd' && $meta->name != 'data' && $meta->name != 'cid' && $meta->name != 'pid' && $meta->name != 'login_timestamp' && $meta->name != 'login_ip'){
        $array[$i] = $meta->name;
        $i++;
      }
    }
    mysql_free_result($result);
    return $array;
  }

  /**
   * 获取会员等级列表
   * @return array
   */
  public function getRanksList()
  {
    global $db;
    static $list = null;
    if (!isset($list)) {
      $cacheId = 'user-ranks';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        $result = $db->query('SELECT * FROM ranks ORDER BY rid');
        $list = $result->allWithKey('rid');
        cache::save($cacheId, $list);
      }
    }
    return $list;
  }

  /**
   * 验证客户是否购买过该产品
   * ps.只有付完款才算购买过该产品
   * @param unknown_type $uid
   * @param unknown_type $pid
   */
  public function validateUserPurchaseProduct($uid, $pid)
  {
      global $db;
      $sql = sprintf("select count(pid) from orders, orders_items where orders.oid = orders_items.oid and orders.status_payment = 1 and uid=%d and pid=%d", $uid, $pid);
      $result = $db->query($sql);
      return $result->one() > 0;
  }
  /**
   * 获取会员等级信息
   * @param int $rid 等级ID
   * @return object
   */
  public function getRankInfo($rid)
  {
    $list = $this->getRanksList();
    return isset($list[$rid]) ? $list[$rid] : false;
  }

  /**
   * 根据等级名称获取会员等级信息
   * @param string $name 等级名称
   * @return object
   */
  public function getRankInfoByName($name)
  {
    global $db;
    static $list = array();
    if (!isset($list[$name])) {
      $result = $db->query('SELECT rid FROM ranks WHERE name = "' . $db->escape($name) . '"');
      $list[$name] = $result->one();
    }
    return $this->getRankInfo($list[$name]);
  }

  /**
   * 新建会员等级
   * @param array $post 表单数组
   * @return int
   */
  public function insertRank($post)
  {
    global $db;
    if (isset($post['name']) && '' == $post['name']) {
      return false;
    }
    $db->insert('ranks', $post);
    cache::remove('user-ranks');
    return $db->lastInsertId();
  }

  /**
   * 修改会员等级
   * @param int $rid 等级ID
   * @param array $post 表单数组
   * @boolean
   */
  public function updateRank($rid, $post)
  {
    global $db;
    if (isset($post['name']) && '' == $post['name']) {
      return false;
    }
    $db->update('ranks', $post, array('rid' => $rid));
    cache::remove('user-ranks');
    return (boolean)$db->affected();
  }

  /**
   * 删除会员等级
   * @param int $rid 等级ID
   * @boolean
   */
  public function deleteRank($rid)
  {
    global $db;
    $db->exec('DELETE FROM ranks WHERE rid = ' . $db->escape($rid));
    cache::remove('user-ranks');
    $db->exec('DELETE FROM products_ranks WHERE rid = ' . $db->escape($rid));
    cache::remove('product-ranks');
    $db->exec('DELETE FROM promotions_products WHERE rid = ' . $db->escape($rid));
    return (boolean)$db->affected();
  }

  /**
   * 获取角色列表
   * @return array
   */
  public function getRolesList($custom = false)
  {
    global $db;
    static $list = null;
    if (!isset($list)) {
      $result = $db->query('SELECT * FROM roles ORDER BY rid');
      $list = $result->allWithKey('rid');
    }
    $result = $list;
    if ($custom) {
      unset($result[self::ROLE_ANONYMOUS_USER], $result[self::ROLE_AUTHENTICATED_USER]);
    }
    return $result;
  }

  /**
   * 获取角色信息
   * @param int $rid 角色ID
   * @return object
   */
  public function getRoleInfo($rid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$rid])) {
      $result = $db->query('SELECT * FROM roles WHERE rid = ' . $db->escape($rid));
      $list[$rid] = $result->row();
    }
    return $list[$rid];
  }

  /**
   * 根据角色名称获取角色信息
   * @param string $name 角色名称
   * @return object
   */
  public function getRoleInfoByName($name)
  {
    global $db;
    static $list = array();
    if (!isset($list[$name])) {
      $result = $db->query('SELECT * FROM roles WHERE name = "' . $db->escape($name) . '"');
      $list[$name] = $result->row();
    }
    return $list[$name];
  }

  /**
   * 获取角色的权限列表
   * @param int $rid 角色ID
   * @return array
   */
  public function getRolePermissions($rid)
  {
    global $db;
    static $list;
    if (!isset($list)) {
      $cacheId = 'roles-permissions';
      if ($cache = cache::get($cacheId)) {
        $list = $cache->data;
      } else {
        $result = $db->query('SELECT permission, rid FROM roles_permissions');
        $rows = $result->all();
        $list = array();
        foreach ($rows as $row) {
          if (!isset($list[$row->rid])) {
            $list[$row->rid] = array();
          }
          $list[$row->rid][] = $row->permission;
        }
        cache::save($cacheId, $list);
      }
    }
    return isset($list[$rid]) ? $list[$rid] : array();
  }

  /**
   * 新建角色
   * @param array $post 表单数组
   * @return int
   */
  public function insertRole($post)
  {
    global $db;
    if (isset($post['name']) && '' == $post['name']) {
      return false;
    }
    $db->insert('roles', $post);
    return $db->lastInsertId();
  }

  /**
   * 修改角色
   * @param int $rid 角色ID
   * @param array $post 表单数组
   * @boolean
   */
  public function updateRole($rid, $post)
  {
    global $db;
    if (isset($post['name']) && '' == $post['name']) {
      return false;
    }
    $db->update('roles', $post, array('rid' => $rid));
    return (boolean)$db->affected();
  }

  /**
   * 删除角色
   * @param int $rid 角色ID
   * @boolean
   */
  public function deleteRole($rid)
  {
    global $db;
    $result = $db->query('SELECT uid FROM users_roles WHERE rid = ' . $db->escape($rid));
    $users = $result->column();
    $db->exec('DELETE FROM roles WHERE rid = ' . $db->escape($rid));
    $db->exec('DELETE FROM users_roles WHERE rid = ' . $db->escape($rid));
    $db->exec('DELETE FROM roles_permissions WHERE rid = ' . $db->escape($rid));
    cache::remove('roles-permissions');
    foreach ($users as $uid) {
      cache::remove('user-roles-' . $uid);
    }
    return (boolean)$db->affected();
  }

  /**
   * 获取权限列表
   * return array
   */
  public function getPermissions()
  {
    return $this->getPermissionsDir(SITESPATH . '/default/controllers');
  }

  /**
   * 递归目录获取权限列表
   * @param string $dir 目录
   * @param string $folder 子目录
   * @return array
   */
  private function getPermissionsDir($dir, $folder = null, $reset = false)
  {
    static $list = array();
    if ($reset) {
      $list = array();
    }
    if ($dh = opendir($dir)) {
      while(false !== ($file = readdir($dh))) {
        if ($file[0] == '.') {
          continue;
        }
        if (is_dir($dir . '/' . $file)) {
          $this->getPermissionsDir($dir . '/' . $file, $file);
        } else {
          include_once $dir . '/' . $file;
          $controllerKey = (isset($folder) ? (ucfirst($folder) . '_') : '') .  ucfirst(basename($file, '.php'));
          $controllerClass = $controllerKey . '_Controller';
          if (class_exists($controllerClass) && method_exists($controllerClass, '__permissions')) {
             $permissions = call_user_func(array($controllerClass, '__permissions'));
             if (is_array($permissions)) {
               if (!isset($list[$controllerKey])) {
                 $list[$controllerKey] = array();
               }
               $list[$controllerKey] += $permissions;
             }
          }
        }
      }
      closedir($dh);
    }
    return $list;
  }

  /**
   * 更新角色权限
   * @param int $rid 角色ID
   * @param array $set 权限列表
   */
  public function updateRolePermissions($rid, $set)
  {
    global $db;
    $db->exec('DELETE FROM roles_permissions WHERE rid = ' . $db->escape($rid));
    foreach ($set as $row) {
      $db->exec('INSERT INTO roles_permissions (rid, permission) VALUES (' . $db->escape($rid) . ', "' . $db->escape($row) . '")');
    }
    cache::remove('roles-permissions');
  }

  /**
   * 获取用户常用地址信息列表
   */
  public function getDeliveryRecordList($uid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$uid])) {
      $db->where('uid', $uid);
      // chenzhigao changed to order by from created to default
      $db->orderby('`default` desc, created desc');
      $result = $db->get('users_delivery_records');
      $list[$uid] = $result->all();
    }
    return $list[$uid];
  }

  public function getDeliveryRecordCount($uid)
  {
     global $db;
     $db->where('uid', $uid);
     $sql = sprintf("select count(rid) from users_delivery_records where uid = %d", $uid);
     $result = $db->query($sql);
     return $result->one();
  }
  /**
   * 获取用户常用地址信息列表
   */
  public function getDeliveryRecordInfo($rid = null, $fitter = array())
  {
  	global $db;
    $array_fitter = array(
      'delivery_name',
      'delivery_mobile',
      'delivery_phone',
      'delivery_email',
      'delivery_postcode',
      'delivery_area',
      'delivery_address',
      'default',
      'uid',
    );
    if (isset($rid)) {
    	$db->where('rid', $rid);
    }
    foreach ($fitter as $k => $v) {
    	if (in_array($k, $array_fitter)) {
    		$db->where($k, $v);
    	}
    }
    $result = $db->get('users_delivery_records');
    return $result->row();
  }

  /**
   * 保存用户常用地址信息
   * @param array $post 表单数组
   */
  public function saveDeliveryRecordInfo($post)
  {
    $result = $this->getDeliveryRecordInfo(null, $post);
  	if (!isset($result) || !$result) {
  		$filter['default'] = 1;
  		$post['default'] = 1;
  		if ($this->getDeliveryRecordInfo(null, $filter)) {
  			$post['default'] = 0;
  		}
  		$this->insertDeliveryRecord($post);
  	}else if($result->delivery_province != $post['delivery_province'] || $result->delivery_country != $post['delivery_country']){
  	  $this->updateDeliveryRecord($result->rid, $post);
  	}
  }

  /**
   * 新增用户常用地址信息
   * @param unknown_type $post
   */
  public function insertDeliveryRecord($post)
  {
    global $db, $user;
    if (!$user->uid) {
      return;
    }
    $set = array(
      'uid' => $user->uid,
      'delivery_first_name' => isset($post['delivery_first_name']) ? $post['delivery_first_name'] : null,
      'delivery_last_name' => isset($post['delivery_last_name']) ? $post['delivery_last_name'] : null,
      'delivery_mobile' => isset($post['delivery_mobile']) ? $post['delivery_mobile'] : null,
      'delivery_phone' => isset($post['delivery_phone']) ? $post['delivery_phone'] : null,
      'delivery_email' => isset($post['delivery_email']) ? $post['delivery_email'] : null,
      'delivery_time' => isset($post['delivery_time']) ? $post['delivery_time'] : null,
      'delivery_postcode' => isset($post['delivery_postcode']) ? $post['delivery_postcode'] : null,
      'delivery_cid' => intval(isset($post['delivery_cid']) ? $post['delivery_cid'] : 0),
      'delivery_pid' => intval(isset($post['delivery_pid']) ? $post['delivery_pid'] : 0),
      'delivery_country' => isset($post['delivery_country']) ? $post['delivery_country'] : null,
      'delivery_province' => isset($post['delivery_province']) ? $post['delivery_province'] : null,
      'delivery_city' => isset($post['delivery_city']) ? $post['delivery_city'] : null,
      'delivery_address' => isset($post['delivery_address']) ? $post['delivery_address'] : null,
      'default' => isset($post['default']) ? $post['default'] : null,
      'created' => TIMESTAMP,
    );
    
    foreach ( $set as $k => $v){
    	$set[$k] = htmlentities($v, ENT_COMPAT, 'UTF-8');
    }
    
    $db->insert('users_delivery_records', $set);
    $rid = $db->lastInsertId();
    if ($rid && $set['default']) {
    	$db->update('users_delivery_records', array('default' => 0), array('rid !=' => $rid));
    }
    return $rid;
  }

  /**
   * 修改用户常用地址信息中的email
   * @param $email 更新的email
   * @param $uid 用户的id
   */
  public function updateDeliveryRecordEmail($email, $uid)
  {
      global $db;
      $db->update('users_delivery_records',array('delivery_email' => $email), array('uid' => $uid));
      return $db->affected() > 0;
  }
  /**
   * 修改用户常用地址信息
   * @param unknown_type $pid
   * @param unknown_type $post
   */
  public function updateDeliveryRecord($rid, $post)
  {
    global $db;
    $set['delivery_first_name'] = isset($post['delivery_first_name']) ? $post['delivery_first_name'] : null;
    $set['delivery_last_name'] = isset($post['delivery_last_name']) ? $post['delivery_last_name'] : null;
    $set['delivery_mobile'] = isset($post['delivery_mobile']) ? $post['delivery_mobile'] : null;
    $set['delivery_phone'] = isset($post['delivery_phone']) ? $post['delivery_phone'] : null;
    $set['delivery_email'] = isset($post['delivery_email']) ? $post['delivery_email'] : null;
    $set['delivery_time'] = isset($post['delivery_time']) ? $post['delivery_time'] : null;
    $set['delivery_postcode'] = isset($post['delivery_postcode']) ? $post['delivery_postcode'] : null;
    $set['delivery_cid'] = isset($post['delivery_cid']) ? $post['delivery_cid'] : null;
    $set['delivery_pid'] = isset($post['delivery_pid']) ? $post['delivery_pid'] : null;
    $set['delivery_country'] = isset($post['delivery_country']) ? $post['delivery_country'] : null;
    $set['delivery_province'] = isset($post['delivery_province']) ? $post['delivery_province'] : null;
    $set['delivery_city'] = isset($post['delivery_city']) ? $post['delivery_city'] : null;
    $set['delivery_address'] = isset($post['delivery_address']) ? $post['delivery_address'] : null;
    $set['default'] = isset($post['default']) ? $post['default'] : null;
    $set['created'] = TIMESTAMP;
    $fitter = array('rid'=> $rid);
    
    
    foreach ( $set as $k => $v){
    	$set[$k] = htmlentities($v, ENT_COMPAT, 'UTF-8');
    }
    
//     $length = count($set);
//     $set = array_map('htmlentities', $set, array_fill(0, $length, ENT_COMPAT), array_fill(0, $length, 'UTF-8') );
    $db->update('users_delivery_records', $set, $fitter);
    $status = $db->affected();
    if ($status && $set['default']) {
      $db->update('users_delivery_records', array('default' => 0), array('rid !=' => $rid));
    }
    return $status;
  }

  /**
   * 删除用户常用地址信息
   * @param unknown_type $sid
   * @param unknown_type $pid
   */
  public function deleteDeliveryRecord($rid, $uid = null)
  {
    global $db;
    $fitter = array('rid' => $rid);
    if (isset($uid)) {
    	$fitter['uid'] = $uid;
    }
    $db->delete('users_delivery_records', $fitter);
    return $db->affected();
  }
  
  /**
   * 更新会员积分
   * @param $integral
   */
  public function updateIntegral($integral)
  {
  	if(!isset($integral) || empty($integral) || !is_numeric($integral)){
  		return false;
  	}
  	global $db,$user;
		if(!$user->uid){
  		return false;
  	}
  	$db->update('users', array('points' => $user->points + $integral), array('uid' => $user->uid));
  	return $db->affected();
  }
  /**
   * 获取会员积分
   * @param $uid 会员ID号
   */
  public function getUserIntegral()
  {
  	global $db,$user;
  	if(!$user->uid){
  		return false;
  	}
  	$db->select('points');
  	$db->where('uid', $user->uid);
  	$result = $db->get('users');
  	return $result->one();
  }
  
  
  public function toggleProductToWishList($filter){
  	global $db,$user;
  	$uid = isset($filter['uid']) ? $filter['uid'] : null;
  	$pid = $filter['pid'];
  	if($uid){
  		$db->where('sid', $user->sid);
  		$db->where('uid', $uid);
  		$db->where('pid', $pid);
  		$result = $db->get('users_wish');
  		$flag = $result->row();
  		$backjson = array();
  		if(!$flag){
  			$params['uid'] = $uid;
  			$params['pid'] = $pid;
  			$params['sid'] = $user->sid;
  			$db->insert('users_wish', $params);
  			$backjson['status'] = 'OK';
  			$backjson['count'] = $this->getWishListCount();
  			return json_encode($backjson);
  		}
  		else{
  			$params['upid'] = $flag->upid;
  			$db->delete('users_wish',$params );
  			$backjson['status'] = 'NOT';
  			$backjson['count'] = $this->getWishListCount();
  			return json_encode($backjson);
  		}
  	}else{
  		$db->where('sid', $user->sid);
  		$db->where('pid', $pid);
  		$result = $db->get('users_wish');
  		$flag = $result->row();
  		$backjson = array();
  		if(!$flag){
  			$params['sid'] = $user->sid;
  			$params['pid'] = $pid;
  			$db->insert('users_wish', $params);
  			$backjson['status'] = 'OK';
  			$backjson['count'] = $this->getWishListCount();
  			return json_encode($backjson);
  		}
  		else{
  			$params['upid'] = $flag->upid;
  			$db->delete('users_wish',$params );
  			$backjson['status'] = 'NOT';
  			$backjson['count'] = $this->getWishListCount();
  			return json_encode($backjson);
  		}
  	}
  	
  	
//   public function toggleProductToWishList($uid, $pid){
  	global $db;
  	$db->where('uid', $uid);
  	$db->where('pid', $pid);
  	$result = $db->get('users_wish');
  	$flag = $result->row();
  	$backjson = array();
  	if(!$flag){
  		$params['uid'] = $uid;
  		$params['pid'] = $pid;
  		$db->insert('users_wish', $params);
  		$backjson['status'] = 'OK';
  		$backjson['count'] = $this->getWishListCount();
  		return json_encode($backjson);
  	}
  	else{
  		$params['upid'] = $flag->upid;
  		$db->delete('users_wish',$params );
  		$backjson['status'] = 'NOT';
  		$backjson['count'] = $this->getWishListCount();
  		return json_encode($backjson);
  	}
  }
  
  public function getWishListCount(){
  	global $db,$user;
  	if(!$user->uid){
  		$db->select('COUNT(*)');
  		$db->where('sid', $user->sid);
  		$db->where('uid', 0);
  		$result = $db->get('users_wish');
  		$back = $result->one();
  		return $back;
  	}
  	else{
  		$db->select('COUNT(*)');
  		$db->where('uid', $user->uid);
  		$result = $db->get('users_wish');
  		$back = $result->one();
  		return $back;
  	}
  }
  
  public function isAddToWishList($pid){
  	global $db,$user;
  	if(!$user->uid){
  		$db->where('sid', $user->sid);
  		$db->where('uid', 0);
  	}else{
  		$db->where('uid', $user->uid);
  	}
  	$db->where('uid', $user->uid);
  	$db->where('pid', $pid);
  	$result = $db->get('users_wish');
  	$flag = $result->row();
  	if($flag){
  		return true;
  	}else{
  		return false;
  	}
  }
  
  public function getWishListProductInfo($page){
  	global $db,$user;
  	$backarray = array();
  	if($user->uid){
  		$db->where('uid', $user->uid);
  	}else{
  		$db->where('sid', $user->sid);
  		$db->where('uid', 0);
  	}
  	
  	$db->select('pid');
  	$db->orderby('upid desc');
  	$db->limitPage(5, $page);
  	$result = $db->get('users_wish');
  	$pidarray = $result->all();
  	
  	$productInstance = Product_Model::getInstance();
  	foreach($pidarray as $pid){
  		$backarray[] = $productInstance->getProductInfo($pid->pid);
  	}
  	return $backarray;
  }
}