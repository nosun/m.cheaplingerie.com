<?php
class WishList_Model extends Bl_Model
{
	/**
	 * @return WishList_Model
	 */
	public static function getInstance()
	{
		return parent::getInstance(__CLASS__);
	}
	
	public function addToWishList($pid, $qty, $data, $isAjax){
		global $db,$user;
		$set['uid'] = $user->uid;
		$set['pid'] = $pid;
		$set['qty'] = isset($qty)? $qty : 1;
		$set['data'] = serialize($data);
		$db->insert("users_wish", $set);
		$upid = $db->lastInsertId();
		if($upid > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function getWishListItemCount(){
		global $db, $user;
		$db->select("count(upid)");
		$db->where("uid", $user->uid);
		$result = $db->get("users_wish");
		return $result->one();
	}
	
	public function getWishListInfoByUid($uid){
		global $db;
		
		$db->select("p.pid, p.type, p.sn, p.name, p.sell_price, p.path_alias, p.filepath, uw.qty, uw.data, uw.upid");
		$db->join("users_wish uw", "uw.pid = p.pid");
		$db->where("uid", $uid);
		$result = $db->get("products p");
		$productList = $result->all();
		
		foreach($productList as $product){
			Product_Model::getInstance()->getProductTypeAndTypeField($product);
			$cartInfo = Cart_Model::getInstance()->getCartProductInfoByPid($product->pid, unserialize($product->data));
			if(!$cartInfo){
				$product->inCart = false;
			}else{
				$product->inCart = true;
			}
		}
		return $productList;
	}
	
	public function getWishListItemInfoByWishId($wishid){
		global $db;
		$db->select("*");
		$db->where("upid", $wishid);
		$result = $db->get("users_wish");
		return $result->all();
	}
	
	public function moveWishToCart($set){
		global $db;
		$cartInfo = Cart_Model::getInstance()->getCartProductInfoByPid($set['pid'], unserialize($set['data']));
		if($cartInfo){
			//  update cart
			return 1;
		}else{
			$db->update("users_wish", array('qty' => $set['qty']), array('upid'=> $set['upid']));
			array_shift($set);
			$db->insert("cart_products", $set);
			return $db->lastInsertId();
		}
		
	}
	
	public function deleteproductfromwishlist($pid){
		global $db,$user;
		$flag = false;
		$db->delete('users_wish',array('uid'=>$user->uid, 'pid'=>$pid));
		return (boolean)$db->affected();
	}
	
	public function getCartItemIdsFromWishListByUid($uid){
		global $db;
		
		$db->select("p.pid, uw.data");
		$db->join("users_wish uw", "uw.pid = p.pid");
		$db->where("uid", $uid);
		$result = $db->get("products p");
		$productList = $result->all();
		
		$cartInstance = Cart_Model::getInstance();
		$cart_item_ids_array = array();
		foreach($productList as $product){
			$cartInfo = $cartInstance->getCartProductInfoByPid($product->pid, unserialize($product->data));
			if($cartInfo){
				$cart_item_ids_array[] = $cartInstance->getCartProductInfoByPid($product->pid, unserialize($product->data))->cart_item_id;
			}
		}
		return $cart_item_ids_array;
	}
}