<?php
class cache
{
  private static function getInstance()
  {
    static $memcache;
    if (!isset($memcache)) {
      $host = Bl_Config::get('cache.memcache.host', 'localhost');
      $port = Bl_Config::get('cache.memcache.port', 11211);
      $memcache = new Memcache;
      $result = $memcache->pconnect($host, $port);
      if (!$result) {
        log::save('memcache', 'Memcache server connect error.');
      }
      return $result ? $memcache : false;
    }
    return $memcache;
  }

  public static function get($cacheId)
  {
    $memcache = self::getInstance();
    if ($memcache) {
      $data = $memcache->get(HOSTNAME . '-' . $cacheId);
      if (false !== $data) {
        $cache = new stdClass();
        $cache->time = 0;
        $cache->data = unserialize($data);
        unset($data);
        return $cache;
      }
    }
    return false;
  }

  public static function save($cacheId, $data, $lifetime = null)
  {
    $memcache = self::getInstance();
    if ($memcache) {
      $lifetime = isset($lifetime) ? intval($lifetime) : Bl_Config::get('cache.lifetime', 180);
      $memcache->set(HOSTNAME . '-' . $cacheId, serialize($data), 0, TIMESTAMP + $lifetime);
    }
  }

  public static function remove($cacheId)
  {
    $memcache = self::getInstance();
    if ($memcache) {
      $memcache->delete(HOSTNAME . '-' . $cacheId);
    }
  }

  public static function clean()
  {
    $memcache = self::getInstance();
    if ($memcache) {
      $memcache->flush();
    }
  }
}
