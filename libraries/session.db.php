<?php
class session
{
  public static function init()
  {
    session_set_save_handler('session::open', 'session::close', 'session::read', 'session::write', 'session::destroy', 'session::gc');
  }
  
  public static function open($save_path, $session_name)
  {
    return true;
  }
  
  public static function close()
  {
    session::gc();
  }
  
  public static function read($sid)
  {
    global $db, $user;
    register_shutdown_function('session_write_close');
    if (!isset($_COOKIE[session_name()])) {
      $user = anonymousUser($sid);
      return '';
    }
    $result = $db->query('SELECT s.data as session_data, s.* , u.* FROM users u INNER JOIN sessions s ON u.uid = s.uid WHERE s.sid = "' . $db->escape($sid) .
      '" AND timestamp >= ' . $db->escape(TIMESTAMP - Bl_Config::get('session.lifetime', 10800)));
    $user = $result->row();
    
    if ($user) {
      $data = $user->session_data;
      unset($user->passwd, $user->session_data);
      if ($user->uid > 0 && $user->status == 1) {
        $userInstance = User_Model::getInstance();
        $user->roles = $userInstance->getUserRoles($user->uid);
        $user->roles[] = User_Model::ROLE_AUTHENTICATED_USER;
        $user->permissions = array();
        $user->data = (isset($user->data) && $user->data) ? unserialize($user->data) : array();
        foreach ($user->roles as $rid) {
          $user->permissions = array_merge($user->permissions, $userInstance->getRolePermissions($rid));
        }
        $user->permissions = array_unique($user->permissions);
      } else {
        $user = anonymousUser($sid);
      }
      return $data;
    } else {
      $user = anonymousUser($sid);
      return '';
    }
  }
  
  public static function write($sid, $data)
  { 
    global $db, $user;
    if (!isset($user) || ($user->uid == 0 && empty($_COOKIE[session_name()]) && empty($data))) {
      return true;
    }
    $uri = '/' . Bl_Core::getUri();
    $db->exec('UPDATE sessions SET uid = ' . $db->escape($user->uid) . ', ip = "' . $db->escape(ipAddress()) .
      '", uri = "' . $db->escape($uri) . '", data = "' . $db->escape($data) . '", timestamp = ' .
      $db->escape(TIMESTAMP) . ' WHERE sid = "' . $db->escape($sid) . '"');
    if (!$db->affected()) {
      $db->exec('INSERT IGNORE INTO sessions (sid, uid, ip, uri, data, timestamp) VALUES ("' . $db->escape($sid) .
        '", ' . $db->escape($user->uid) . ', "' . $db->escape(ipAddress()) . '", "' . $db->escape($uri) . '", "' .
        $db->escape($data) . '", ' . $db->escape(TIMESTAMP) . ')');
    }

    return true;
  }
  
  public static function destroy($sid)
  {
    global $db;
    $db->exec('DELETE FROM sessions WHERE sid = "' . $db->escape($sid) . '"');
    return true;
  }
  
  public static function gc()
  {
    global $db;
    $db->exec('DELETE FROM sessions WHERE timestamp < ' . $db->escape(TIMESTAMP - Bl_Config::get('session.lifetime', 10800)));
    $db->disconnect();
    return true;
  }
  
  public static function count($timestamp = 0, $hasAnonymous = true)
  {
    global $db;
    if (!$hasAnonymous) {
      $cond = ' AND uid > 0';
    } else {
      $cond = '';
    }
    $result = $db->query('SELECT COUNT(0) FROM sessions WHERE timestamp > ' . $timestamp . $cond);
    return $result->one();
  }
}
