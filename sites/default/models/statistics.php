<?php
class Statistics_Model extends Bl_Model
{
  /**
   * @return Content_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }
  
  /**
   * 
   * 会员统计
   * return 会员总数、有订单会员数
   */
  public function membersSta()
  {
    global $db;
    $cacheId = 'membersta';
    if ($cache = cache::get($cacheId)) {
      $userSta = $cache->data;
    } else {
      $result = $db->query("SELECT COUNT(0) FROM users");
      $userSta[0] = $result->one();
      $result = $db->query("SELECT COUNT(DISTINCT(uid)) FROM orders WHERE uid != 0");
      $userSta[1] = $result->one();
      cache::save($cacheId, $userSta);
    }
    return $userSta;
  }
  
  /**
   * 
   * 订单统计
   * return 订单总数、匿名会员订单数
   */
  public function ordersSta($startTime = null, $endTime = null)
  {
    global $db;
    $cacheId = 'orderssta';
    if ($cache = cache::get($cacheId)) {
      $ordersSta = $cache->data;
    } else {
      $sqladd = '';
      if ($startTime) {
        $sqladd .= ' AND created > ' . $startTime;
      }
      if ($endTime) {
        $sqladd .= ' AND created < ' . $endTime;
      }
      $result = $db->query("SELECT COUNT(0) FROM orders WHERE 1" . $sqladd);
      $ordersSta[0] = $result->one();
      $result = $db->query("SELECT COUNT(0) FROM orders WHERE uid = 0" . $sqladd);
      $ordersSta[1] = $result->one();
      cache::save($cacheId, $ordersSta);
    }
    return $ordersSta;
  }
  
  /**
   * 
   * 金额统计
   * return 购物金额、匿名会员订单金额、已支付的订单金额
   */
  public function amountSta($startTime = null, $endTime = null)
  {
    global $db;
    $cacheId = 'amountsta';
    if ($cache = cache::get($cacheId)) {
      $amountSta = $cache->data;
    } else {
      $sqladd = '';
      if ($startTime) {
        $sqladd .= ' AND created > ' . $startTime;
      }
      if ($endTime) {
        $sqladd .= ' AND created < ' . $endTime;
      }
      $result = $db->query("SELECT SUM(pay_amount) FROM orders WHERE 1 " . $sqladd);
      $amountSta[0] = $result->one();
      $result = $db->query("SELECT SUM(pay_amount) FROM orders WHERE uid = 0" . $sqladd);
      $amountSta[1] = $result->one();
      $result = $db->query("SELECT SUM(pay_amount) FROM orders WHERE status_payment = 1" . $sqladd);
      $amountSta[2] = $result->one();
      cache::save($cacheId, $amountSta);
    }
    return $amountSta;
  }
  
  /**
   * 
   * 商品点击统计
   * return 商品点击次数统计
   */
  public function clickSta()
  {
    global $db;
    $cacheId = 'clicksta';
    if ($cache = cache::get($cacheId)) {
      $clicksta = $cache->data;
    } else {
      $result = $db->query("SELECT SUM(visits) FROM products");
      $clicksta = $result->one();
      cache::save($cacheId, $clicksta);
    }
    return $clicksta;
  }
  
  /**
   * 
   * 商品销售排行
   * return 商品名称  货号  销售量   销售额   均价
   */
  public function salesSta($page = 1, $pageRows = 20)
  {
    global $db;
    $cacheId = 'salessta';
    if ($cache = cache::get($cacheId)) {
      $return = $cache->data;
    } else {
      $result = $db->query("SELECT COUNT(DISTINCT(pid)) FROM orders_items");
      $count = $result->one();
      $start = $pageRows * ($page - 1);
      $limit = 'LIMIT ' . $start . ', ' . $pageRows;
      $result = $db->query("SELECT name,sn, SUM(qty) num ,SUM(pay_price*qty) pay_price FROM orders_items WHERE pid != 0 GROUP BY pid ORDER BY num DESC " . $limit);
      $list = $result->all();
      $return = array($count, $list);
      cache::save($cacheId, $return);
    }
    return $return;
  }
  
  /**
   * 
   * 会员订单排行
   * return 会员名   订单数(单位:个)   购物金额
   */
  public function usersordersSta($page = 1, $pageRows = 20)
  {
    global $db;
    $cacheId = 'usersorderssta';
    if ($cache = cache::get($cacheId)) {
      $return = $cache->data;
    } else {
      $result = $db->query("SELECT COUNT(DISTINCT(uid))  FROM orders WHERE uid != 0");
      $count = $result->one();
      $start = $pageRows * ($page - 1);
      $limit = 'LIMIT ' . $start . ', ' . $pageRows;
      $result = $db->query("SELECT uid, COUNT(0) num ,SUM(pay_amount) pay_amount FROM orders WHERE uid != 0 GROUP BY uid ORDER BY num DESC " . $limit);
      $list = $result->all();
      foreach ($list as $k => $v) {
        $result = $db->query("SELECT NAME FROM users WHERE uid = " . $v->uid);
        $v->name = $result->one();
      }
      $return = array($count, $list);
      cache::save($cacheId, $return);
    }
    return $return;
  }
  
  /**
   * 
   * 商品访问购买率
   * return 商品名称  人气指数  购买次数  访问购买率
   */
  public function buybannerSta($page, $pageRows)
  {
    global $db;
    $cacheId = 'buybannersta';
    if ($cache = cache::get($cacheId)) {
      $return = $cache->data;
    } else {
      $result = $db->query("SELECT COUNT(*)  FROM products WHERE visits > 0");
      $count = $result->one();
      $start = $pageRows * ($page - 1);
      $limit = 'LIMIT ' . $start . ', ' . $pageRows;
      $result = $db->query("SELECT pid,name,visits,transactions FROM products WHERE visits > 0 ORDER BY visits DESC " . $limit);
      $list = $result->all();
      $return = array($count, $list);
      cache::save($cacheId, $return);
    }
    return $return;
  }
}