<?php
class log
{
  private static function getPath()
  {
    static $path = null;
    if (!isset($path)) {
      $path = Bl_Config::get('log.path', false);
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
  
  public static function save($type, $message = null, $data = null)
  {
    global $user;
    $path = self::getPath();
    if (!$path) {
      return;
    }
    $file = $path . $type . '_log';
    if (is_file($file) && !is_writable($file)) {
      return;
    }
    $fp = fopen($file, 'a');
    if ($fp) {
      fwrite($fp, sprintf("%s\t%s\t%s\t%s\t%s\t%s\t%s" . PHP_EOL, $type, date('Y-m-d H:i:s', TIMESTAMP), ipAddress(), $user->name, '/' . Bl_Core::getUri(), strip_tags($message), isset($data) ? serialize($data) : ''));
      fclose($fp);
    }
  }
}
