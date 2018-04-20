<?php
require_once("api_common.php");

class Api_Order_Controller extends Api_Model
{
	private $_orderInstance;
	private $_userInstance;

	public static function __funcs()
	{
		return array(
		'order_update',
		'order_communicate',
		'get_refund_amount',
		);
	}

	public function __construct()
	{
		$this->_userInstance = User_Model::getInstance();
		$this->_orderInstance = Order_Model::getInstance();
	}
	
	public function order_update($updateData, $number){
		if(isset($updateData['addOrderItem'])){
        	
      } else {
        if (!$orderInfo = $this->_orderInstance->getOrederInfoByNumber($number)) {
        	//if no such an order.
        	return 'Error, order info by number error.';
        }
        
        log::save('DEBUG_API_ORDER', 'updateData[data]', serialize($updateData['data']));
        log::save('DEBUG_API_ORDER', 'updateData', serialize($updateData));
        
        if(isset($updateData['data'])){
        		$updateData['data'] = unserialize($updateData['data']);
        }else if($orderInfo->data != ''){
        		$updateData['data'] = unserialize($orderInfo->data);
        }else{
        		$updateData['data'] = array();
        }
        log::save('DEBUG_API_ORDER', 'updateData', serialize($updateData));
        
        
        $this->_orderInstance->updateOrder($orderInfo->oid, $updateData);
        
        //After update, get the order again for the latest information.
        $orderInfo = $this->_orderInstance->getOrderInfo($orderInfo->oid);
        $oiids = array();
        if(isset($updateData['p_sns'])){
        	foreach ($orderInfo->items as $index=>$orderItem){
        		if(in_array($orderItem->sn, $updateData['p_sns'])){
        			$oiids[] = $orderItem->oiid;
        		}else{
        			unset($orderInfo->items[$index]);
        		}
        	}
        }
        if (isset($updateData['shipping_no'])) {
        	$updateData['data']['shipping_no'] = $updateData['shipping_no'];
        }
        
        if(isset($updateData['data']['shipping_no']) && $updateData['data']['shipping_no'] != ''){
          //send email for shipping number.
          $emailSetting = Bl_Config::get('orderShippingNoEmail', array(0));
          if ($emailSetting['status']) {
            $stmpSetting = Bl_Config::get('stmp', 0);
            if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
            	$mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
              $email[] = $updateData['delivery_email'];
              $siteInfo = Bl_Config::get('siteInfo', array());
              if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
              	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          	  }
          	  
          	  $orderInfo->data['shipping_no'] = $updateData['data']['shipping_no'];
          	  if(count($oiids) > 0){
          	  	$this->_orderInstance->updateOrderItemShippingNo($oiids, $updateData['data']['shipping_no']);
          	  }
          	  $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
              
          
              $customerInfo = User_Model::getInstance()->getUserInfo($orderInfo->uid);
              $customerName = isset($customerInfo->name)?$customerInfo->name : $customerInfo->email;
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'], $customerName)) {
                return 'Success';
              } else {
                return 'Error';
              }
            } else {
              return 'Error';
            }
          }
        }
        
        // send order tailoring email
        if(isset($updateData['status']) && intval($updateData['status']) == 1) {
        	$emailSetting = Bl_Config::get('orderTailoringEmail', array(0));
        	if ($emailSetting['status']) {
        		$stmpSetting = Bl_Config::get('stmp', 0);
        		if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
        			$mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
        			$email[] = $updateData['delivery_email'];
        			$siteInfo = Bl_Config::get('siteInfo', array());
        			if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
        				$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
        			}
        			 
        			$orderInfo->estimated_delivery_date = $updateData['estimated_delivery_date'];
        			
        			$emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
        			$emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
        	
        	
        			$customerInfo = User_Model::getInstance()->getUserInfo($orderInfo->uid);
        			$customerName = isset($customerInfo->name)?$customerInfo->name : $customerInfo->email;
        			if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'], $customerName)) {
        				return 'Success';
        			} else {
        				return 'Error';
        			}
        		} else {
        			return 'Error';
        		}
        	}
        }
        //currently not send payment status changed email when status changed from Paid into Refunded or Prtially Refunded.
        if (isset($updateData['status_payment']) && $updateData['status_payment'] == '1') {
          $emailSetting = Bl_Config::get('orderPayEmail', array(0));
          if ($emailSetting['status']) {
            $stmpSetting = Bl_Config::get('stmp', 0);
            if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
              $mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
              $email[] = $updateData['delivery_email'];
              $siteInfo = Bl_Config::get('siteInfo', array());
              if(key_exists('ccadmin', $emailSetting) && $emailSetting['ccadmin'] == '1'){
              	$email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
          	  }
          	  $emailSetting['title'] = $mailInstance->ReplaceMailToken($emailSetting['title'], $orderInfo);
              $emailSetting['content'] = $mailInstance->ReplaceMailToken($emailSetting['content'], $orderInfo);
              if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
                return 'Success';
              } else {
                return 'Error';
              }
            } else {
            	return 'Error';
            }
          }
        }
      }
      return 'Success';
	}

	/**
	 * 订单沟通
	 * Enter description here ...
	 * @param string $number 订单的number
	 * @param array $stockItems sn=>qty;
	 */
    public function order_communicate($number, $stockItems) {
        $stockoutItemsInfoList = array();
        $refundAmount = $this->get_refund_amount($number, $stockItems, $orderInfo, $stockoutItemsInfoList);
        if (!isset($refundAmount->product)) {
            return $refundAmount;
        }
        $ret = $this->send_refund_email($orderInfo, $stockoutItemsInfoList, $refundAmount);
        return $ret;
    }
    
    public function get_refund_amount($number, $stockItemList, &$orderInfo = null, &$stockoutItemsInfoList = array()) {
        if (!$orderInfo = $this->_orderInstance->getOrederInfoByNumber($number)) {
            //if no such an order.
            return 'error:order info by number error.';
        }
        $goodsWeight = 0.0;
        $stockGoodsWeight = 0.0;    //可以满足的产品重量
        $goodsAmount = 0.0;
        $stockGoodsAmount = 0.0;    //可以满足的产品总价
        $goodsNumber = 0;
        $stockGoodsNumber = 0;      //可以满足的产品数量
        $stockItemsInfoList = array();
        $refundAmount = new stdClass;
        $refundAmount->product = 0.0;
        $refundAmount->shipping = 0.0;
       
        foreach ($orderInfo->items as $index => $orderItem) {
            if (key_exists($orderItem->sn, $stockItemList)) {
                $qty = $stockItemList[$orderItem->sn];
                if ($qty > 0) {
                    $stockItemsInfoList[] = $orderItem;
                }
                if ($qty < $orderItem->qty) {
                    $orderItem->lack_qty = $orderItem->qty - $qty;
                    $refundAmount->product += $orderItem->pay_price * ($orderItem->lack_qty);
                    $stockoutItemsInfoList[] = $orderItem;
                    $stockItemList[$orderItem->sn] = 0;
                    $stockGoodsWeight += $orderItem->wt * $qty;
                    $stockGoodsAmount += $orderItem->pay_price * $qty;
                    $stockGoodsNumber += $qty;
                } else {
                  $stockItemList[$orderItem->sn] -= $orderItem->qty;
                  $stockGoodsWeight += $orderItem->wt * $orderItem->qty;
                  $stockGoodsAmount += $orderItem->price * $orderItem->qty;
                  $stockGoodsNumber += $orderItem->qty;
                }
            } else {
                $orderItem->lack_qty = $orderItem->qty;
                $refundAmount->product += $orderItem->pay_price * ($orderItem->lack_qty);
                $stockoutItemsInfoList[] = $orderItem;
            }
            $goodsWeight += $orderItem->wt * $orderItem->qty;
            $goodsAmount += $orderItem->pay_price * $orderItem->qty;
            $goodsNumber += $orderItem->qty;
        }
        /* disable shipping amount caculate
        require_once SITESPATH . '/default/controllers/cart.php';
        $cartCotroller = new Cart_Controller();
        $shippingAmount = $cartCotroller->getshippingmoneyAction($orderInfo->shipping_method, $goodsWeight, $goodsAmount, $goodsNumber, $orderInfo->delivery_cid, $orderInfo->delivery_pid, false);
        $stockShippingAmount = $cartCotroller->getshippingmoneyAction($orderInfo->shipping_method, $stockGoodsWeight, $stockGoodsAmount, $stockGoodsNumber, $orderInfo->delivery_cid, $orderInfo->delivery_pid, false);
        $refundAmount->shipping = $shippingAmount - $stockShippingAmount;
        */
        return $refundAmount;
    }
    private function send_refund_email($orderInfo, $stockoutItemList, $refundAmount) {
        $siteInfo = Bl_Config::get('siteInfo', array());
        $siteName = $siteInfo['sitename'];
        $siteUrl = $siteInfo['siteurl'];
        
        $stockoutItemInfo = $this->generate_order_items_table($stockoutItemList, true);
        $orderItemInfo = $this->generate_order_items_table($orderInfo->items, false);
        //$shippingRefundAmount = c($refundAmount->shipping);
        //$totalRefundAmount = c($refundAmount->product + $refundAmount->shipping);
        $productRefundAmount = c($refundAmount->product);
        $refundEmailTitleTemplate = "Out of stock items of order: $orderInfo->number";
        $refundEmailContentTemplate = <<< EOT
            <div style="width:800px">
            Dear $orderInfo->delivery_first_name $orderInfo->delivery_last_name:<br/>
            <br/>
            Thank you very much for your order on <a href="$siteUrl" >$siteName</a>.We are working hard to offer the best lingerie products to you.
            But as you know, this is a busy season for sales, and we regret to inform you that some of items in your order are out of stock.<br/>
            <br/>
            These items are listed below:<br/>
            $stockoutItemInfo
            We have two recommendations on how to resolve this issue:<br/><br/>
            1. Fulfill and ship the items we have in stock once we receive your confirmation. Of course, we would refund the fees for 
               the out of stock items back to your account with related shipping fees.
               For your case, we would refund $productRefundAmount plus the extra shipping fees to you. Please refer to your PayPal account.<br/>
            2. You can cancel this order and we will refund the full amount to you.<br/>
            <br/>
            If we do not receive a response from you in two working days, we would:<br/>
             - Use Option 1 (fulfill and ship the rest items) by default.<br/>
             - Use Option 2 (cancel order) if more than 1/3 of the items are out of stock.<br/>
            <br/>
            We sincerely apologize that these items are out of stock, and are looking forward to receiving your response as soon as possible
            to let us know how to proceed. We are refreshing our stocks bi-weekly, and now building a larger inventory to meet your needs. Welcome to visit us again, your satisfaction is the
            greatest motivation to help us improve our service.<br/>
            <br/> 
            <div style="color:gray;font-size:small">
            Best Regards<br/>
            $siteName customer support team<br/>
            </div>
            <br/>
            ===========================  $orderInfo->number Details =================================<br/>
            $orderItemInfo
            </div>
EOT;
        $stmpSetting = Bl_Config::get('stmp', 0);
        
        if ($stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd']) {
            $mailInstance = isset($mailInstance) ? $mailInstance : new Mail_Model($stmpSetting);
            
            $emailSetting = array();
            $emailSetting['title'] = $refundEmailTitleTemplate;
            $emailSetting['content'] = $refundEmailContentTemplate;
            $customerInfo = User_Model::getInstance()->getUserInfo($orderInfo->uid);
            $customerName = isset($customerInfo->name)?$customerInfo->name : $customerInfo->email;
            $email[] = $customerInfo->email;
            $email[] = isset($siteInfo['email']) ? $siteInfo['email'] : null;
            if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], 'html', $customerName)) {
                return 'success';
            } else {
                return 'error: send email failed.';
            }
        } else {
            return 'error: smtp config error';
        }
    }

    private function generate_order_items_table($orderItemList, $isStockoutItemList = false) {
        $orderItemInfo = '';
        if (!empty($orderItemList)) {
            $orderItemInfo = '<table cellpadding="0" cellspacing="0" style="font-size:12px;">';
            $orderItemInfo .= '<th width="100">Image</th><th width="300">Desc</th><th width="100">Quantity<th width="100">Price</th><th width="100">Subtotal</th>';
            foreach ($orderItemList as $orderItem) {
                $itemDataInfo = '';
                if (isset($orderItem->data) && $orderItem->data != '') {
                    foreach ($orderItem->data as $k1 => $v1) {
                        $itemDataInfo .= "<br/><strong style='text-transform:capitalize;'>" . $k1 . ":</strong><span>&nbsp;</span>&nbsp;&nbsp;<span style='text-transform:capitalize;'>" . $v1 . "</span>";
                    }
                }
                $itemUrl = url($orderItem->url);
                $itemImageUrl = urlimg('admin_product_album', $orderItem->filepath);
                $itemQty = $isStockoutItemList ? $orderItem->lack_qty : $orderItem->qty;
                $itemPrice = c($orderItem->pay_price);
                $itemTotalAmount = $isStockoutItemList ? c($orderItem->pay_price * $itemQty) : c($orderItem->total_amount);
                $itemInfo = <<< ITEM
                            <tr>
                                <td align="center" style="border-bottom: 1px solid #ddd;">
                                    <a href="$itemUrl"><img style="border: medium none;padding:5px 10px 5px 0;" src="$itemImageUrl"/></a>
                                </td>
                                <td align="left" style="border-bottom: 1px solid #dddddd;">
                                    <a href="$itemUrl">$orderItem->name;</a>
                                    $itemDataInfo
                                </td>
                                <td align="center" style="border-bottom: 1px solid #ccc;"><span>$itemQty</span></td>
                                <td align="center" style="border-bottom: 1px solid #ccc;"><span>$itemPrice</span></td>
                                <td align="center" style="border-bottom: 1px solid #ccc;"><span>$itemTotalAmount</span></td>
                                </tr>
ITEM;
                $orderItemInfo .= $itemInfo;
            }
            $orderItemInfo .= "</table><br/>";
        }
        return $orderItemInfo;
    }
}

