<?php
class Cart_Model extends Bl_Model
{
  /**
   * @return Cart_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取购物车商品列表
   * @param unknown_type $sid
   */
  public function getCartProductList($cart_item_id = null, $page = 1, $pageRows = null, $ifStatShipping = true)
  {
    global $db, $user;
    $sid = $user->sid;
    static $list = array();
    //if (!isset($list[$sid])) {
      $list[$sid] = new stdClass();
      if ($user->uid != 0) {
        $db->where('uid', $user->uid);
      } else {
        $db->where('sid', $sid);
        $db->where('uid', 0);
      }
      if ($cart_item_id) {
        $db->where('cart_item_id IN', $cart_item_id);
      }
      $db->orderby('cart_item_id DESC');
      if (isset($pageRows)) {
        $db->limitPage($pageRows, $page);
      }
      $result = $db->get('cart_products c');
      $products = $result->all();
      foreach ($products as $k => &$product) {
        $productInstance = Product_Model::getInstance();
        $productinfo = $productInstance->getProductInfo($product->pid);
        if ($productinfo) {
          $product->sell_min = $productinfo->sell_min;
          $product->sell_max = $productinfo->sell_max;
          $product->free_shipping = $productinfo->free_shipping;
          $product->sell_price = $productinfo->sell_price;
          $product->price = $productinfo->price;
          //added by pzzhang.
          $product->list_price = $productinfo->list_price;
          //end added by pzzhang.
          $product->path_alias = $productinfo->path_alias;
          $product->wt = $productinfo->wt;
          $product->tpath_alias = isset($productinfo->tpath_alias) ? $productinfo->tpath_alias : 'product';
          $product->description = $productinfo->description;
          $product->name = $productinfo->name;
          $product->filepath = $productinfo->filepath;
          $product->url = $product->path_alias . '-p' . $productinfo->sn . '.html';
          $product->data = unserialize($product->data);
          callFunction('getSellPrice', $product);
          if ($ifStatShipping || !$product->free_shipping) {
            $list[$sid]->goods_number = (isset($list[$sid]->goods_number) ? $list[$sid]->goods_number : 0) + $product->qty;
            $list[$sid]->goods_weight = (isset($list[$sid]->goods_weight) ? $list[$sid]->goods_weight : 0) + $product->wt * $product->qty;
          }
          $list[$sid]->goods_amount = (isset($list[$sid]->goods_amount) ? $list[$sid]->goods_amount : 0) + $product->price * $product->qty;
          //added by pzzhang.
          $list[$sid]->total_save_amount = (isset($list[$sid]->total_save_amount) ? $list[$sid]->total_save_amount : 0) 
                                            + ($product->list_price - $product->price) * $product->qty;
          //end added by pzzhang.
          
        }
      }
      $list[$sid]->product = $products;
    //}

    return $list[$sid];
  }

  /**
   * 获取购物车商品数量
   */
  public function getCartCount()
  {
    global $db, $user;
    $sid = $user->sid;
    if (!$sid) {
      return;
    }
    $db->select('COUNT(0)');
    if ($user->uid != 0) {
      $db->where('c.uid', $user->uid);
    } else {
      $db->where('c.sid', $sid);
      $db->where('c.uid', 0);
    }
    $result = $db->get('cart_products c');
    return $result->one();
  }
  /**
   * 获取购物车中单独物品信息
   */
  public function getCartProductInfoByPid($pid, $data = null)
  {
    global $db, $user;
    if (!$user->sid) {
      return;
    }
    if ($user->uid != 0) {
      $db->where('uid', $user->uid);
    } else {
      $db->where('sid', $user->sid);
      $db->where('uid', 0);
    }
    if (isset($data) && $data) {
      $data = serialize($data);
      $db->where('data', $data);
    } else {
      $db->where('data', null);
    }
    $db->where('pid', $pid);
    $result = $db->get('cart_products');
    return $result->row();
  }

  /**
   * 获取购物车中单独物品信息
   */
  public function getCartProductInfo($cart_item_id, $data = null)
  {
    global $db, $user;
    if (!$user->sid) {
      return;
    }
    if ($user->uid != 0) {
      $db->where('uid', $user->uid);
    } else {
      $db->where('sid', $user->sid);
      $db->where('uid', 0);
    }
    if (isset($data)) {
      $db->where('data', $data);
    }
    $db->where('cart_item_id', $cart_item_id);
    $result = $db->get('cart_products');
    return $result->row();
  }

  /**
   * 新增商品到购物车
   * @param unknown_type $post
   */
  public function insertProductToCart($post)
  {
    global $db, $user;
    $set = array(
      'sid' => $user->sid,
      'uid' => $user->uid,
      'pid' => isset($post['pid']) ? intval($post['pid']) : null,
      'qty' => isset($post['qty']) ? intval($post['qty']) : 0,
      'changed' => TIMESTAMP,
      'data' => isset($post['data']) && $post['data'] ?  serialize($post['data']) : null,
    );
    if ($set['qty']) {
      $db->insert('cart_products', $set);
    }
    return $db->lastInsertId();
  }

  /**
   * 修改购物车中商品信息
   * @param unknown_type $pid
   * @param unknown_type $post
   */
  public function updateCartProduct($cart_item_id, $post)
  {
    global $db, $user;
    $set = array(
      'qty' => isset($post['qty']) ? $post['qty'] : null,
      'changed' => TIMESTAMP,
      'data' => isset($post['data']) && $post['data'] ? serialize($post['data']) : null,
    );
    if ($user->uid != 0) {
      $fitter = array('cart_item_id'=> intval($cart_item_id), 'uid' => $user->uid);
    } else {
      $fitter = array('cart_item_id'=> intval($cart_item_id), 'sid' => $user->sid, 'uid' => 0);
    }
    $set = array_filter($set, "Common_Model::filterArray");
    if ($set['qty']) {
      $db->update('cart_products', $set, $fitter);
    } else {
      $db->delete('cart_products', $fitter);
    }
    return $db->affected();
  }

  public function addCartProductComment($cart_item_id, $comment)
  {
      global $db, $user;
      $productInfo = $this->getCartProductInfo($cart_item_id);
      if (isset($productInfo))
      {
          $data =  unserialize($productInfo->data);
          $data["comment"] = $comment;
          $set = array(
              "data" => serialize($data));
          if ($user->uid != 0) {
              $filter = array('cart_item_id'=> intval($cart_item_id), 'uid' => $user->uid);
          } else {
              $filter = array('cart_item_id'=> intval($cart_item_id), 'sid' => $user->sid, 'uid' => 0);
          }
          $db->update('cart_products', $set, $filter);
          return $db->affected();
      }
      else
      {
          return 0;
      }
  }
  
  /**
   * 删除购物车中的商品
   * @param unknown_type $sid
   * @param unknown_type $pid
   */
  public function deleteCartProduct($cart_item_id)
  {
    global $db, $user;
    if ($user->uid != 0) {
      $fitter = array('cart_item_id IN'=> $cart_item_id, 'uid' => $user->uid);
    } else {
      $fitter = array('cart_item_id IN'=> $cart_item_id, 'sid' => $user->sid, 'uid'=>0);
    }
    $db->delete('cart_products', $fitter);
    return $db->affected();
  }

  /**
   * 合并购物车
   */
  public function mergeCart($sid)
  {
  	global $db, $user;
  	if (!$user->uid) {
  		return;
  	}
  	$db->where('sid', $sid);
  	$db->where('uid', $user->uid);
  	$result = $db->get('cart_products');

  	$list = $result->all();
  	foreach ($list as $k => $v) {
      $db->where('pid', $v->pid);
      $db->where('uid', $user->uid);
      $db->where('data', $v->data);
      $result = $db->get('cart_products');
      $final_list = $result->row();
      
      if (!$final_list) {
        //add a new line for cart_product info.
      	$db->update('cart_products', array('uid' => $user->uid), array('cart_item_id' => $v->cart_item_id, 'data'=> $v->data));
      } else if(!($final_list->cart_item_id == $v->cart_item_id)){
        //do nothing. They are the same column.
      	$db->update('cart_products', array('qty' => ($v->qty + $final_list->qty)), array('cart_item_id' => $final_list->cart_item_id));
      	$db->delete('cart_products', array('cart_item_id' => $v->cart_item_id));
      }
  	}
  }

  /**
   * 清除会话购物车
   * @param string $sid 会话ID
   */
  public function deleteCart($sid = null)
  {
    global $db, $user;
    if (!isset($sid)) {
      $sid = $user->sid;
    }
    $db->exec('DELETE FROM cart_products WHERE sid = "' . $sid . '"');
  }
}