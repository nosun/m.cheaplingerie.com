<?php
class log
{
  public static function save($type, $message = null, $data = null)
  {
    global $db, $user;
    if (!Bl_Config::get('log', true)) {
      return;
    }
    $set = array(
      'type' => $type,
      'uid' => isset($user->uid) ? $user->uid : 0,
      'message' => $message,
      'data' => isset($data) ? (is_array($data) || is_object($data) ? serialize($data) : $data) : '',
      'ip' => ipAddress(),
      'uri' => '/' . Bl_Core::getUri(),
      'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
      'timestamp' => TIMESTAMP,
    );
    $db->insert('log', $set);
  }
}
