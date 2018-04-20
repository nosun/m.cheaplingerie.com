<?php
class cache
{
  private static function getInstance()
  {
    static $rediscache;
    if (!isset($rediscache)) {
      $host = Bl_Config::get('cache.redis.host', 'localhost');
      $port = Bl_Config::get('cache.redis.port', 6379);
      $rediscache = new Redis();
      $result = $rediscache->connect($host, $port);
      if (!$result) {
        log::save('rediscache', 'Rediscache server connect error.');
      }
      return $result ? $rediscache : false;
    }
    return $rediscache;
  }

  public static function get($cacheId)
  {
    $rediscache = self::getInstance();
    if ($rediscache) {
      $data = $rediscache->get(HOSTNAME . '-' . $cacheId);
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
    $rediscache = self::getInstance();
    if ($rediscache) {
      $lifetime = isset($lifetime) ? intval($lifetime) : Bl_Config::get('cache.lifetime', 180);
      $rediscache->setex(HOSTNAME . '-' . $cacheId, $lifetime, serialize($data));
    }
  }

  public static function remove($cacheId)
  {
    $rediscache = self::getInstance();
    if ($rediscache) {
      $rediscache->del(HOSTNAME . '-' . $cacheId);
    }
  }

  public static function clean()
  {
    $rediscache = self::getInstance();
    if ($rediscache) {
      $rediscache->flushDB();
    }
  }
}
