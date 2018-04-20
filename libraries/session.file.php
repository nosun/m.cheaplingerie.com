<?php
class session
{
  private static function getPath()
  {
    static $path = null;
    if (!isset($path)) {
      $path = Bl_Config::get('session.path', false);
      if ($path) {
        strtr($path, '\\', '/');
        if ($path[strlen($path) - 1] != '/') {
          $path .= '/';
        }
        if (!is_writable($path)) {
          $path = false;
        }
      } else {
        $path = false;
      }
    }
    return $path;
  }
  
  private static function getFile($sid)
  {
    return self::getPath() . $sid . '.sid';
  }
  
  public static function init()
  {
    $path = self::getPath();
    if (!$path) {
      throw new Bl_General_Exception('Session path is invalid.');
    }
    session_save_path($path);
    session_set_save_handler('session::open', 'session::close', 'session::read', 'session::write', 'session::destroy', 'session::gc');
  }
  
  public static function open($savePath, $sessionName)
  {
    return true;
  }
  
  public static function close()
  {
    return true;
  }
  
  public static function read($sid)
  {
    global $db, $user;
    register_shutdown_function('session_write_close');
    $file = self::getFile($sid);
    if (!isset($_COOKIE[session_name()]) || !is_file($file) || !($session = unserialize(file_get_contents($file)))) {
      $user = anonymousUser($sid);
      return '';
    }
    $result = $db->query('SELECT * FROM users WHERE uid = ' . $db->escape($session['uid']));
    $user = $result->rowObject();
    $user->sid = $sid;
    $user->ip = ipAddress();
    $user->data = $session['data'];
    if ($user && $user->uid > 0 && $user->status == 1) {
      // TODO: 载入角色
    } else {
      $user = anonymousUser($sid, isset($user->data) ? $user->data : '');
    }
    return $user->data;
  }
  
  public static function write($sid, $data)
  {
    global $user;
    if ($user->uid == 0 && empty($_COOKIE[session_name()]) && empty($data)) {
      return true;
    }
    $uri = '/' . Bl_Core::getUri();
    $set = array(
      'uid' => $user->uid,
      'ip' => ipAddress(),
      'uri' => $uri,
      'data' => $data,
    );
    file_put_contents(self::getFile($sid), serialize($set));
    return true;
  }
  
  public static function destroy($sid)
  {
    $file = self::getFile($sid);
    if (is_file($file)) {
      unlink($file);
    }
    return true;
  }
  
  public static function gc($lefttime)
  {
    return true;
  }
  
  public static function count($timestamp = 0)
  {
    return 0;
  }
}
