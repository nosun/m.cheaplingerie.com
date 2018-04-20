<?php
class Order_Model extends Bl_Model
{
  const ORDER_PENDING = 0;
  const ORDER_RPOCESSING = 1;
  const ORDER_COMPLETE = 2;
  const ORDER_CANCEL = -1;
  const ORDER_DEL = -2;
  const ORDER_ABNOMAL = -3; // 未生效的团购订单

  const FEE_TAXES = 1;
  const FEE_SUPPORT = 2;
  const FEE_SALES_PROMOTION = 3;
  const FEE_RISE = 4;
  const FEE_SHIPPING = 5;

  public $fee = array(
    'taxes' => 1,
    'support' => 2,
    'sales_promotion' => 3,
    'rise' => 4,
    'shipping' => 5,
  );

  public $feeName = array(
    'support' => '保价',
    'taxes' => '税金',
    'sales_promotion' => '促销',
    'rise' => '涨价',
    'shipping' => '运费',
  );

  static $orderDscripe = array(
    self::ORDER_PENDING => 'Pedding',
    self::ORDER_RPOCESSING => 'Processing',
    self::ORDER_COMPLETE => 'complete',
    self::ORDER_CANCEL => 'Cancel',
    self::ORDER_DEL => 'Ordel Del',
  );

  /**
   * @return Order_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   *
   * 获取订单列表
   * @param array $post
   * @param int $page
   * @param int $pageRows
   */
  public function getOrdersList($post = array(), $page, $pageRows, $isvalid = false)
  {
    global $db;
    $filter = array(
        'number' => isset($post['number']) ? trim($post['number']) : null,
        'delivery_email' => isset($post['delivery_email']) ? trim($post['delivery_email']) : null,
        'uid LIKE' => isset($post['uid']) ? trim($post['uid']) : null,
        'created >=' => isset($post['startTime']) ? strtotime($post['startTime']) : null,
        'created <=' => isset($post['endTime']) && $post['endTime'] ? strtotime($post['endTime']) + 3600*24 : null,
        'status' => isset($post['status']) ? $post['status'] : null,
        'status_payment' => isset($post['status_payment']) ? $post['status_payment'] : null,
        'status_shipping' => isset($post['status_shipping']) ? $post['status_shipping'] : null,
    );
    $router = Bl_Core::getRouter();
    if (!$filter['status']) {
      $filter['status !='] = self::ORDER_DEL;
    }
    foreach ($filter as $key => $value) {
      if (isset($value) && $value !== '' && $value !== false) {
        if($key == 'oid LIKE'){
          $value = '%'.$value.'%';
        }
        $db->where('o.'.$key, $value);
      }
    }
    if (isset($post['username']) && !empty($post['username'])) {
      $db->join('users u', 'u.uid = o.uid' ,'left');
      $db->where('u.name LIKE', '%'.$post['username'].'%');
    }
    if($isvalid){
    	$db->where('o.status', '> -1');
    }
    $db->orderby('o.created DESC');
    $db->limitPage($pageRows, $page);
    $result = $db->get('orders o');
    return $result->allWithKey('oid');
  }

  /**
   *
   * 获取订单总数
   * @param array $post
   */
  public function getOrdersCount($post = array())
  {
    global $db;
    $db->select('COUNT(0)');
    $filter = array(
          'number' => isset($post['number']) ? trim($post['number']) : null,
          'delivery_email' => isset($post['delivery_email']) ? trim($post['delivery_email']) : null,
          'uid LIKE' => isset($post['uid']) ? trim($post['uid']) : null,
          'created >=' => isset($post['startTime']) ? strtotime($post['startTime']) : null,
          'created <=' => isset($post['endTime']) ? strtotime($post['endTime']) : null,
          'status' => isset($post['status']) ? $post['status'] : null,
          'status_payment' => isset($post['status_payment']) ? $post['status_payment'] : null,
          'status_shipping' => isset($post['status_shipping']) ? $post['status_shipping'] : null,
      );
	  	if (!$filter['status']) {
	      $filter['status !='] = self::ORDER_DEL;
	    }
      foreach ($filter as $key => $value) {
      	if (isset($value) && $value !== '' && $value !== false) {
          if($key == 'oid LIKE'){
            $value = '%'.$value.'%';
          }
          $db->where('o.'.$key, $value);
        }
      }
      if (isset($post['username']) && !empty($post['username'])) {
        $db->join('users u', 'u.uid = o.uid' ,'left');
        $db->where('u.name LIKE', '%'.$post['username'].'%');
      }
    $result = $db->get('orders o');
    return $result->one();
  }

  /**
   *
   * 获取订单信息（根据oid）
   * @param int $o
   */
  public function getOrderInfo($oid)
  {
    global $db;
    //static $list = array();
    $list = array();
    if (!isset($list[$oid])) {
      $cacheId = 'order-' . $oid;
      if ($cache = cache::get($cacheId)) {
        $list[$oid] = $cache->data;
      } else {
		    $db->where('oid', $oid);
		    $result = $db->get('orders');
		    $orderInfo = $result->row();
		    if ($orderInfo) {
		      $orderInfo->weight = $this->getOrderWeight($oid);
		      $orderInfo->items = $this->getOrderItems($oid);
		      $orderInfo->fees = $this->getOrderFee($oid);
		      if (false === ($orderInfo->data = unserialize($orderInfo->data))) {
		        $orderInfo->data = array();
		      }
		    }
		    $list[$oid] = $orderInfo;
		    cache::save($cacheId,  $list[$oid]);
      }
    }
    return $list[$oid];
  }
  /**
   * 根据订单编号获取 ID
   */
  public function getOrederInfoByNumber($number)
  {
  	global $db;
  	$db->where('number', $number);
    $result = $db->get('orders');
    $row = $result->row();
    $oid = intval($row->oid);
    return $this->getOrderInfo($oid);
  }

  /**
   *
   * 获取订单下商品信息
   * @param int $oid
   */
  public function getOrderItems($oid)
  {
    global $db;
    $db->select('p.*,oi.*, oi.data data');
    $db->where('oi.oid', $oid);
    $db->where('oi.status', 1);
    $db->join('products p', 'p.pid = oi.pid');
    $result = $db->get('orders_items oi');
    $products = $result->allWithKey('oiid');
    foreach ($products as &$product) {
      $product->directory_tid =  $product->directory_tid4 ? $product->directory_tid4 : (
            $product->directory_tid3 ? $product->directory_tid3 : (
              $product->directory_tid2 ? $product->directory_tid2 : (
                $product->directory_tid1 ? $product->directory_tid1 : 0
              )
            )
          );
      $taxonomyInstance = Taxonomy_Model::getInstance();
      $termInfo = $taxonomyInstance->getTermInfo($product->directory_tid);
      $product->tpath_alias = $termInfo->path_alias;
      /*modified for product url change*/
      $product->url = ($product->path_alias !== '' ? $product->path_alias : $product->pid).'-p'.$product->sn . '.html';
      /*$product->url = ($product->tpath_alias ? $product->tpath_alias : 'product') . '/' .
        ($product->path_alias ? $product->path_alias : $product->pid) . '.html';*/
      $product->data = unserialize($product->data);
    }
    return $products;
  }

  /**
   *
   * 获取订单税金、保价、促销等信息
   * @param int $oid
   */
  public function getOrderFee($oid){
    global $db;
    $db->where('oid', $oid);
    $db->orderby('orders_feeid');
    $result = $db->get('orders_fee');
    return $result->allWithKey('fee_key');
  }

  /**
   *
   * 获取订单中所有商品的总重量
   * @param int $oid
   */
  public function getOrderWeight($oid)
  {
    global $db;
    $db->select("SUM(wt_amount) weight");
    $db->where('oid', $oid);
    $result = $db->get('orders_items');
    return $result->one();
  }

  /**
   *
   * 更新订单中商品的状态（-1 表示删除的状态）
   * @param int $filter
   */
  public function updateStatusOrderItem($oid, $oiid){
    global $db;
    $db->update('orders_items', array('status' => -1), array('oiid' => $oiid));
    if ($db->affected()) {
      return $this->getOrderStatistics($oid, 0, 1);
    }
  }

  /**
   *
   * 更新订单信息
   * @param int $order_id
   */
  public function updateOrder($oid, $post)
  {
    global $db;
      $post['fee_amount'] = 0;
    if(isset($post['fees'])){
      foreach($post['fees'] as $k=>$v){
        if(isset($v['fee_value'])){
          $post['fee_amount'] += $v['fee_value'];
        }
      }
    }
    $post['pay_amount'] = $post['total_amount'] + $post['fee_amount'];
    $set = array(
      'status' => isset($post['status']) ? $post['status'] : null,
      'status_payment' => isset($post['status_payment']) ? $post['status_payment'] : null,
      'status_shipping' => isset($post['status_shipping']) ? $post['status_shipping'] : null,
      'shipping_method' => isset($post['shipping_method']) ? $post['shipping_method'] : null,
      'delivery_first_name' => isset($post['delivery_first_name']) ? $post['delivery_first_name'] : '',
      'delivery_last_name' => isset($post['delivery_last_name']) ? $post['delivery_last_name'] : '',
      'delivery_mobile' => isset($post['delivery_mobile']) ? $post['delivery_mobile'] : null,
      'delivery_phone' => isset($post['delivery_phone']) ? $post['delivery_phone'] : null,
      'delivery_email' => isset($post['delivery_email']) ? $post['delivery_email'] : null,
      'delivery_time' => isset($post['delivery_time']) ? $post['delivery_time'] : null,
      'delivery_postcode' => isset($post['delivery_postcode']) ? $post['delivery_postcode'] : null,
      'delivery_city' => isset($post['delivery_city']) ? $post['delivery_city'] : '',
      'delivery_address' => isset($post['delivery_address']) ? $post['delivery_address'] : null,
      'delivery_time' => isset($post['delivery_time']) ? $post['delivery_time'] : null,
      'pay_amount' => isset($post['pay_amount']) ? $post['pay_amount'] : null,
      'fee_amount' => isset($post['fee_amount']) ? $post['fee_amount'] : null,
      'data' => isset($post['data']) && !empty($post['data']) ? serialize($post['data']) : '',
      'updated' => TIMESTAMP,
      'payment_time' => isset($post['payment_time']) ? $post['payment_time'] : null,
    );
    $set = array_filter($set, "Common_Model::filterArray");
    $db->update('orders', $set, array('oid' => $oid));
    cache::remove('order-' . $oid);
    $this->updateOrderItem($oid, $post);
    $set_fee = isset($post['fees']) ? $post['fees'] : array();
    $this->updateOrderFee($oid, $set_fee);
    $this->getOrderStatistics($oid, 1, 1);
  }

 /**
   *
   * 新增订单信息
   * @param int $order_id
   */
  public function insertOrder($post)
  {
    global $db, $user;
    
    $post['fee_amount'] = 0;
    if(isset($post['fees'])){
      foreach($post['fees'] as $k=>$v){
        if(isset($v['fee_value'])){
          $post['fee_amount'] += $v['fee_value'];
        }
      }
    }

    $post['pay_amount'] = (isset($post['total_amount']) ? $post['total_amount'] : 0) + (isset($post['fee_amount']) ? $post['fee_amount'] : 0);
    $ip = ipAddress();
    $set = array(
      'status' => isset($post['status']) ? $post['status'] : 0,
      'uid' => isset($user->uid) ? $user->uid : 0,
      'status_payment' => isset($post['status_payment']) ? $post['status_payment'] : 0,
      'status_shipping' => isset($post['status_shipping']) ? $post['status_shipping'] : 0,
      'shipping_method' => isset($post['shipping_method']) ? $post['shipping_method'] : '',
      'payment_method' => isset($post['payment_method']) ? $post['payment_method'] : '',
      'delivery_first_name' => isset($post['delivery_first_name']) ? $post['delivery_first_name'] : '',
      'delivery_last_name' => isset($post['delivery_last_name']) ? $post['delivery_last_name'] : '',
      'delivery_mobile' => isset($post['delivery_mobile']) ? $post['delivery_mobile'] : '',
      'delivery_phone' => isset($post['delivery_phone']) ? $post['delivery_phone'] : '',
      'delivery_email' => isset($post['delivery_email']) ? $post['delivery_email'] : '',
      'delivery_time' => isset($post['delivery_time']) ? $post['delivery_time'] : '',
      'delivery_postcode' => isset($post['delivery_postcode']) ? $post['delivery_postcode'] : '',
      'delivery_city' => isset($post['delivery_city']) ? $post['delivery_city'] : '',
      'delivery_cid' => intval(isset($post['delivery_cid']) ? $post['delivery_cid'] : 0),
	  'delivery_country' => isset($post['delivery_country']) ? $post['delivery_country'] : '',
	  'delivery_pid' => intval(isset($post['delivery_pid']) ? $post['delivery_pid'] : 0),
	  'delivery_province' => isset($post['delivery_province']) ? $post['delivery_province'] : '',
      'delivery_address' => isset($post['delivery_address']) ? $post['delivery_address'] : '',
      'pay_amount' => isset($post['pay_amount']) ? $post['pay_amount'] : 0,
      'fee_amount' => isset($post['fee_amount']) ? $post['fee_amount'] : 0,
      'data' => isset($post['data']) && !empty($post['data']) ? serialize($post['data']) : null,
      'created' => TIMESTAMP,
      'created_ip' => $ip,
    );
    $set['number'] = hasFunction('orderSn') ? callFunction('orderSn') : $this->getOrderSn();
    $productInstance = Product_Model::getInstance();
    $stockCheck = Bl_Config::get('stockCheck', false);
    if ($stockCheck) {
      $pidqty = $this->mergerRepetitive($post['pids'], $post['qtys']);
      $db->exec('LOCK TABLE orders WRITE, products WRITE');
      if (isset($pidqty) && $pidqty) {
        foreach ($pidqty as $k => $qty) {
          // 检查库存
          if (!$productInstance->checkProductStock($k, $qty)) {
            $db->exec('UNLOCK TABLES');
            $productInfo = $productInstance->getProductInfo($pid);
    	      return array(-3, '库存不足，商品 ' . $productInfo->name . ' 库存为 ' . $productInfo->stock);
          }
        }
      }
    }
    $db->insert('orders', $set);
    $oid = $db->lastInsertID();
    if ($oid) {
      if ($stockCheck) {
        // 更新库存
        if (isset($pidqty) && $pidqty) {
          foreach ($pidqty as $k => $qty) {
            $productInstance->updateProductStock($k, $qty);
          }
        }
        $db->exec('UNLOCK TABLES');
      }

      $this->insertOrderItemStart($oid, $post);
      $set_fee = isset($post['fees']) ? $post['fees'] : array();
      $this->updateOrderFee($oid, $set_fee);
      $this->getOrderStatistics($oid, 1, 1);
      return array(true, $oid);
    } else {
      if ($stockCheck) {
        $db->exec('UNLOCK TABLES');
      }
      return array(false, '订单生成失败');
    }
  }

  public function mergerRepetitive($pids, $qtys){
    $newpids = array();
    foreach ($pids as $k => $v) {
      if (isset($newpids[$v])) {
        $newpids[$v] += $qtys[$k];
      } else {
        $newpids[$v] = $qtys[$k];
      }
    }
    return $newpids;
  }

  /**
   *
   * 生成新订单时产品信息
   * @param unknown_type $oid
   * @param unknown_type $post
   */
  private function insertOrderItemStart($oid, $post) {
    global $db, $user;
    $productInstance = Product_Model::getInstance();
    foreach ($post['cart_item_ids'] as $k => $v) {
    	if (!$v) {
    		unset($post['cart_item_ids'][$k]);
    	}
    }
    if (isset($post['cart_item_ids']) && $post['cart_item_ids']) {
      foreach ($post['cart_item_ids'] as $k => $cart_item_id) {
        $db->select('*');
        $db->where('cart_item_id', $cart_item_id);
        if ($user->uid != 0) {
          $c = 'uid = ' . $user->uid;
        } else {
          $c = 'sid = "' . $db->escape($user->sid) . '"';
        }
        $result = $db->get('cart_products');
        $list = $result->row();
        if (isset($list->pid) && $list->pid) {
          $productInfo = $productInstance->getProductInfo($list->pid);
          $productInfo->data = $list->data;
          $productInfo->qty = $post['qtys'][$k];
          $this->insertOrderItem($oid, $productInfo);
        }
      }
    } else if (isset($post['pids']) && $post['pids']) {
      foreach ($post['pids'] as $k => $pid) {
        $list = $productInstance->getProductInfo($pid);
        $list->qty = $post['qtys'][$k];
        $this->insertOrderItem($oid, $list);
      }
    }
  }

  /**
   *
   * 更新订单额外（税金、保价等的信息）
   * @param int $oid
   * @param array $post
   */
  private function updateOrderFee($oid, $post)
  {
    global $db;
    foreach ($post as $key => $v) {
      $set['oid'] = $oid;
      $set['fee_key'] = $key;
      $set['fee_type'] = isset($this->fee[$key]) && $this->fee[$key] ? $this->fee[$key] : 0 ;
      $set['fee_value'] = isset($v['fee_value']) && $v['fee_value'] ? $v['fee_value'] : 0;
      $set['description'] = isset($v['description']) && $v['description'] ? $v['description'] : '';
      if (isset($v['orders_feeid']) && $v['orders_feeid']) {
        $db->where('orders_feeid' , $v['orders_feeid']);
      } else {
        $db->where('fee_key' , $key);
        $db->where('oid' , $set['oid']);
      }
      $result = $db->get('orders_fee');
      if ((boolean)$result->one()) {
        unset($set['fee_name']);
        if ($v['orders_feeid']) {
          $con = array('orders_feeid' => $v['orders_feeid']);
        } else {
          $con = array('fee_type' => $set['fee_type'], 'oid' => $set['oid']);
        }
        $db->update('orders_fee', $set, $con);
      } else {
        $set['fee_name'] = isset($v['fee_name']) ? $v['fee_name'] : $this->feeName[$key];
        $db->insert('orders_fee', $set);
      }
    }
  }

 /**
   *
   * 更新订单状状态
   * @param int $oid
   */
  public function updateStatusOrder($oid, $post)
  {
    global $db;
    $set = array(
      'status' => isset($post['status']) ? $post['status'] : null,
      'status_payment' => isset($post['status_payment']) ? $post['status_payment'] : null,
      'status_shipping' => isset($post['status_shipping']) ? $post['status_shipping'] : null,
      'payment_method' => isset($post['payment_method']) ? $post['payment_method'] : null,
      'payment_time' => isset($post['payment_time']) ? $post['payment_time'] : null,
      'updated' => TIMESTAMP,
    );
    $set = array_filter($set, "Common_Model::filterArray");
    cache::remove('order-' . $oid);
    $db->update('orders', $set, array('oid' => $oid));
    return $db->affected();
  }

  /**
   *
   * 更新订单商品信息
   * @param array $post
   */
  private function updateOrderItem($oid, $post){
    global $db;
    if(isset($post['oiid'])) {
      foreach($post['oiid'] as $k => $v) {
        $set = array(
          'qty' => $post['item_number'][$k],
          'pay_price' => $post['item_pay_price'][$k],
          'total_amount' => $post['item_pay_price'][$k] * $post['item_number'][$k],
        );
        $db->update('orders_items', $set, array('oiid' => $v));
      }
    }
  }

  /** 新增订单中商品
   * @param int $oid
   * @param array $post
   */
  public function insertOrderItem($oid, $post)
  {
    global $db;
    callFunction('getSellPrice', $post);
    $db->select('oiid');
    $db->where('oid', $oid);
    $db->where('pid', $post->pid);
    if (isset($post->data) && $post->data) {
      $db->where('data', $post->data);
    }
    $result = $db->get('orders_items');
    $oiid = $result->one();
    $post->qty = isset($post->qty) ? $post->qty : 1;
    
    $temp = unserialize($post->data);
    if( strpos($temp['Color'], ' Picture') != false ){
    	$temp['Color'] = 'As Picture' . substr($temp['Color'], strpos($temp['Color'], ':'));
    }
    $post->data = serialize($temp);
    
    //$discount = Bl_Config::get('discount', 1);
    $set = array(
        'oid' => $oid,
        'pid' => $post->pid,
        'sn' => $post->sn,
        'type' => $post->type,
        'number' => $post->number,
        'qty' => $post->qty,
        'name' => $post->name,
        /*added by pzzhang*/
    	'wt' => $post->wt,
        'wt_amount' => $post->wt * $post->qty,
    	/*end added by pzzhang*/
        'sell_price' => $post->price,
        //'pay_price' => $post->price * $discount,  // add for discount
    	'pay_price' => $post->price,
        //'total_amount' => $post->price * $discount * $post->qty,// add for discount
    	'total_amount' => $post->price * $post->qty,
        'status' => 1,
        'data' => isset($post->data) ? $post->data : null,
      );
    if (!(boolean)$oiid) {
      $db->insert('orders_items', $set);
      if ($db->affected()) {
        $this->getOrderStatistics($oid, 0, 1);
      }
    } else {
      $db->update('orders_items', array('qty' => array('value'=> qty + 1, 'escape' => false)), array('oiid' => $oiid));
      if ($db->affected()) {
        $this->getOrderStatistics($oid, 0, 1);
      }
    }
    $db->exec("UPDATE products SET transactions = transactions + " . $set['qty'] . " WHERE pid = '" . $set['pid'] . "'");
  }

  /**
   *
   * 重新计算订单价格
   * @param int $oid
   * @param int $isfee
   * @param int $ifItem
   */
  private function getOrderStatistics($oid, $isfee = 1, $ifItem = 1)
  {
    global $db;
    if ($isfee) {
      $db->select('SUM(fee_value)');
      $db->where('oid', $oid);
      $result = $db->get('orders_fee');
      //$set['fee_amount'] = (boolean)$result->one() ? $result->one() : 0;
      $set['fee_amount'] = $result->one();
    }
    if($ifItem){
      $db->select('SUM(pay_price*qty)');
      $db->where('oid', $oid);
      $db->where('status', 1);
      $result = $db->get('orders_items');
      $set['total_amount'] = $result->one();

    }
    $db->where('oid', $oid);
    $result = $db->get('orders');
    $orderInfo = $result->row();
    $set['fee_amount'] = isset($set['fee_amount']) ? $set['fee_amount'] : $orderInfo->fee_amount;
    if(!isset($set['total_amount'])){
    	log::save('debug', 'total_amount_notset', $set);
    }
    $set['total_amount'] = isset($set['total_amount']) ? $set['total_amount'] : $orderInfo->fee_amount;
    $set['pay_amount'] = $set['fee_amount'] + $set['total_amount'];
    $set['updated'] = TIMESTAMP;
    $db->update('orders', $set, array('oid' => $oid));
    cache::remove('order-' . $oid);
    return $db->affected();
  }

  /**
   *
   * 获取搜索后条件显示
   * @param array $filter
   */
  public function getSelectHtml($filter)
  {
    $selectHtml = '';
    $filter = array_filter($filter, "Common_Model::filterArray");
    if(!empty($filter)) {
      $selectHtml = '<b>'.t('Select Term').'（<a href="'.url('admin/order/firstList/all').'">'.t('Clear Away').'</a>）：</b>';
      foreach ($filter as $key => $dl) {
        if (isset($dl) && $dl != '') {
          $selectHtml .= $this->getSelectHtmlCeil($key, $dl).' ';
        }
      }
    }
    return $selectHtml;
  }

  /**
   *
   * 获取搜索后条件显示具体实现
   * @param string $key
   * @param string $value
   */
  public function getSelectHtmlCeil($key, $value = "")
  {
    switch ($key){
      case 'number' : return '<span>'.t('Order OrderId').'（'.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
      case 'username' : return '<span>'.t('User').'（'.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
      case 'startTime' : return '<span>'.t('Order Creat Time').'（> '.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
      case 'endTime' : return '<span>'.t('Order Creat Time').'（< '.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
      case 'status' : return '<span>'.t('Order Status').'（'.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
      case 'status_payment' : return '<span>'.t('Order Pay Status').'（'.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
      case 'status_shipping' : return '<span>'.t('Order Ship Status').'（'.$value.'）<a href="'.url('admin/order/firstList/'.$key).'">×</a></span>';
    }
  }

  /**
   * 生成订单的编号
   */
  public function getOrderSn()
  {
    global $db;
    $number = '';
    $orderpr = Bl_Config::get('order.prefix');
    $db->select('number');
    //FIX created time is the same.
    $db->orderby('oid DESC');
    $db->limit(1);
    $result = $db->get('orders');
    $num = $result->one();
    
    //get auto increment id.
    $result = $db->query('SHOW TABLE STATUS LIKE "orders"');
    $data = $result->row();
    
    
    $today = date('Ymd',TIMESTAMP);
    /**/
    $number = $orderpr . $today .'-'. $data->Auto_increment;
    /*if ($num && (substr($num, -12, 8) == $today)) {
      $i = intval(substr($num, -4)) + 1;
      $number = $orderpr . $today . sprintf('%04d', $i);
    }*/
    return $number;
  }

  /**
   * 支付后调用 callback 函数
   */
  public function dealOrderStatus($result)
  {
  	$oid = $result['orderNumber'];
  	if ($result['verified'] == 1) {
  		$orderInfo = $this->getOrederInfoByNumber($oid);
  		if ($result['paidAmount'] != $orderInfo->pay_amount) {
  			return -2;
  		}
  		if ($this->updateStatusOrder($orderInfo->oid, array('status_payment' => 1, 'payment_method' => $result['payment_method'], 'payment_time' => time()))) {
  			return true;
  		}
  	} else {
  		return -1;
  	}
  }

  public function getOrderToken()
  {
    return isset($_SESSION['ordertoken']) ? $_SESSION['ordertoken'] : null;
  }

  public function saveOrderToken()
  {
  	return $_SESSION['ordertoken'] = randomString(16);
  }

  public function clearOrderToken()
  {
    unset($_SESSION['ordertoken']);
  }

}


