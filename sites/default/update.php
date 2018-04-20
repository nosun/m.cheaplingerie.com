<?php

function update_0001()
{
  global $db;
  $sql = "ALTER TABLE `page_variables` CHANGE `meta_keywords` `meta_keywords` TEXT NULL ,     CHANGE `var1` `var1` MEDIUMTEXT NULL ,     CHANGE `var2` `var2` MEDIUMTEXT NULL ,     CHANGE `var3` `var3` MEDIUMTEXT NULL ,     CHANGE `var4` `var4` MEDIUMTEXT NULL ,     CHANGE `var5` `var5` MEDIUMTEXT NULL ,     CHANGE `var6` `var6` MEDIUMTEXT NULL ;";
  $db->exec($sql);
}

function update_0002()
{
  global $db;
  $taxonomyInstance = Taxonomy_Model::getInstance();
  $termsList = $taxonomyInstance->getTermsList_back(Taxonomy_Model::TYPE_DIRECTORY);
  $db->exec("ALTER TABLE `terms` ADD COLUMN `ptid1` INT(10) DEFAULT '0' NULL AFTER `vid`, ADD COLUMN `ptid2` INT(10) DEFAULT '0' NULL AFTER `ptid1`, ADD COLUMN `ptid3` INT(10) DEFAULT '0' NULL AFTER `ptid2`;");
  $db->exec("ALTER TABLE `terms` ADD INDEX `tids` (`ptid1`, `ptid2`, `ptid3`);");
  foreach ($termsList as $v) {
    if (is_array($v->sub)) {
      $ptid1 = $v->tid;
      foreach ($v->sub as $v2) {
        $db->update('terms', array('ptid1' => $ptid1), array('tid' => $v2->tid));
        if (is_array($v2->sub)) {
          $ptid2 = $v2->tid;
          foreach ($v2->sub as $v3) {
            echo $ptid1 . '-' . $ptid2 . '-' . $v3->tid.'<br>';
            $db->update('terms', array('ptid1' => $ptid1, 'ptid2' => $ptid2), array('tid' => $v3->tid));
          }
        }
      }
    }
  }
  $db->exec("DROP TABLE `terms_hierarchy`;");
}

function update_0003()
{
  global $db;
  $db->exec("ALTER TABLE `products` ADD COLUMN `directory_tid1` INT(10) DEFAULT '0' NULL AFTER `directory_tid`, ADD COLUMN `directory_tid2` INT(10) DEFAULT '0' NULL AFTER `directory_tid1`, ADD COLUMN `directory_tid3` INT(10) DEFAULT '0' NULL AFTER `directory_tid2`, ADD COLUMN `directory_tid4` INT(10) DEFAULT '0' NULL AFTER `directory_tid3`;");
  $db->exec("ALTER TABLE `products` ADD INDEX `directory_tids` (`directory_tid1`, `directory_tid2`, `directory_tid3`, `directory_tid4`);");
  $result = $db->query('SELECT tid, ptid1, ptid2, ptid3 FROM terms');
  $terms = $result->allWithKey('tid');
  foreach ($terms as $tid => $termInfo) {
    $productTids = array();
    if (!$termInfo->ptid1) {
      $productTids['directory_tid1'] = $termInfo->tid;
    } else if (!$termInfo->ptid2) {
      $productTids['directory_tid1'] = $termInfo->ptid1;
      $productTids['directory_tid2'] = $termInfo->tid;
    } else if (!$termInfo->ptid3) {
      $productTids['directory_tid1'] = $termInfo->ptid1;
      $productTids['directory_tid2'] = $termInfo->ptid2;
      $productTids['directory_tid3'] = $termInfo->tid;
    } else {
      $productTids['directory_tid1'] = $termInfo->ptid1;
      $productTids['directory_tid2'] = $termInfo->ptid2;
      $productTids['directory_tid3'] = $termInfo->ptid3;
      $productTids['directory_tid4'] = $termInfo->tid;
    }
    $db->update('products', $productTids, array('directory_tid' => $tid));
  }
  $db->exec("ALTER TABLE `products` DROP COLUMN `directory_tid`;");
}

function update_0004()
{
  global $db;
  $db->exec("DELETE FROM settings WHERE `key` = 'userRegisterEmail';");
  $db->exec("DELETE FROM settings WHERE `key` =  'orderCancelEmail';");
  $db->exec("DELETE FROM settings WHERE `key` = 'orderPayEmail';");
  $db->exec("DELETE FROM settings WHERE `key` = 'getPasswordEmail';");
  $db->exec("DELETE FROM settings WHERE `key` =  'deliverGoodsEmail';");
  $db->exec("DELETE FROM settings WHERE `key` = 'orderTradingEmail';");
  
  $db->exec("INSERT INTO `settings` (`key`, `value`) VALUES
('deliverGoodsEmail', 'a:4:{s:6:\"status\";s:1:\"1\";s:5:\"title\";s:21:\"Shipment notification\";s:7:\"content\";s:692:\"<p>Dear {[user.name]}!</p>\r\n<p>Your order&nbsp;<strong> {[order.number]}</strong>&nbsp; have delivery on<strong> {[time]}</strong> according to the address you provided.&nbsp; If the order<strong>&nbsp; {[order.number]}</strong>&nbsp;&nbsp; is the right, please click <a href=\"{[site.siteurl]}order/list/\" target=\"_blank\">link</a>&nbsp; to confirm it when you get the products.</p>\r\n<p>&nbsp;If you do not get the products, you can give us a message. Click URL to <a href=\"{[site.siteurl]}\" target=\"_blank\">our store</a>.</p>\r\n<p>&nbsp;</p>\r\n<p>Thanks to your support, welcome back again!</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p>URL:{[site.siteurl]}<br /></p>\r\n<p>Email:{[site.email]}</p><br />\";s:4:\"type\";s:4:\"html\";}'),
('getPasswordEmail', 'a:4:{s:6:\"status\";s:1:\"1\";s:5:\"title\";s:13:\"Password back\";s:7:\"content\";s:212:\"<p>Dear customer ！<br /><strong><br /></strong><br />Change you passwordusing this link:&nbsp; {[url]}<br /></p>\r\n<p>Sincerely,</p>\r\n<p>&nbsp;</p>\r\n<p>url:{[site.siteurl]}</p>\r\n<p>Email:{[site.email]}</p><br />\";s:4:\"type\";s:4:\"html\";}'),
('orderPayEmail', 'a:4:{s:6:\"status\";s:1:\"1\";s:5:\"title\";s:14:\"Payment notice\";s:7:\"content\";s:333:\"<p>Dear {[user.name]}</p>\r\n<p>&nbsp;</p>\r\n<p>You pay for your order on<strong> {[time]}</strong>，the order number is <strong>{[order.number]}</strong>. We will deliver the products within the shortest time. Please note the delivery mail！</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p>URL:{[site.siteurl]}</p>\r\n<p>Email:{[site.email]}</p>\";s:4:\"type\";s:4:\"html\";}'),
('userRegisterEmail', 'a:4:{s:6:\"status\";s:1:\"1\";s:5:\"title\";s:33:\"Thank you for creating an account\";s:7:\"content\";s:390:\"<p><strong>Thank you for creating an account at {[site.siteurl]}. </strong></p>\r\n<p><strong>Your Account Details</strong></p>\r\n<p>User Name: <strong>{[user.name]}</strong></p>\r\n<p>Login to you account using this link: <a href=\"{[site.siteurl]}user/login/\">{[site.siteurl]}user/login/</a> </p>\r\n<p>&nbsp;</p>\r\n<p>Site Information</p>\r\n<p>URL:{[site.siteurl]}</p>\r\n<p>Email:{[site.email]}</p>\";s:4:\"type\";s:4:\"html\";}'),
('orderTradingEmail', 'a:4:{s:6:\"status\";s:1:\"1\";s:5:\"title\";s:9:\"New Order\";s:7:\"content\";s:2139:\"<p>Dear {[user.name]} !<br /><br />&nbsp;<br /><br />Thanks for shopping with <strong>{[site.siteurl]} </strong>!</p>\r\n<p>The following are the details of your order.</p>\r\n<p>&nbsp;</p>\r\n<p>------------------------------------------------------<br /><br />Order Number:&nbsp; <strong>{[order.number]}<br /><br /></strong>Order Date:&nbsp; <strong>{[time]}</strong></p>\r\n<p><br /><br />------------------------------------------------------<br /><strong>Order Product(s)</strong></p>\r\n<p>{[order.items]}<br /><br />&nbsp;------------------------------------------------------<br /><strong>Delivery Address</strong><br /><br /><br />E-mail: <strong>{[order.delivery_email]}<br /></strong></p>\r\n<p>First Name:<strong> {[order.delivery_first_name]}<br /></strong></p>\r\n<p>Last Name:<strong> {[order.delivery_last_name]}</strong></p>\r\n<p>Contact mobile:<strong> {[order.delivery_phone]}</strong></p>\r\n<p>Telephone:<strong> {[order.delivery_mobile]}<br /></strong></p>\r\n<p>City: <strong>{[order.delivery_city]}<br /></strong></p>\r\n<p>Country :<strong> {[order.delivery_country]}</strong></p>\r\n<p>Province: <strong>{[order.delivery_province]}<br /></strong></p>\r\n<p>Address: <strong>{[order.delivery_address]} </strong></p>\r\n<p>Post Code : <strong>{[order.delivery_postcode]}<br /></strong></p>\r\n<p>Delivery time: <strong>{[order.delivery_time]}</strong></p>\r\n<p>------------------------------------------------------</p>\r\n<p><strong>Payment Method :&nbsp;<br /><br />{[order.payment_method]}<br /></strong><br />------------------------------------------------------<br /><strong>&nbsp;Distribution Method</strong></p>\r\n<p><strong>{[order.shipping_method]}</strong></p>\r\n<p>------------------------------------------------------</p>\r\n<p>This email is an automatically email when you submit the order. </p>\r\n<p>You can track your order status from the below link:</p>\r\n<p><a href=\"{[site.siteurl]}order/list/\" target=\"_blank\" list=\"\" order=\"\" siteurl=\"\" site=\"\" http=\"\" _blank=\"\">myorder</a>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p>Best regards</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p>URL:{[site.siteurl]}</p>\r\n<p>Email:{[site.email]}</p><br />\";s:4:\"type\";s:4:\"html\";}'),
('orderCancelEmail', 'a:4:{s:6:\"status\";s:1:\"1\";s:5:\"title\";s:12:\"order cancle\";s:7:\"content\";s:470:\"<p>Dear {[user.name]}<br /></p>\r\n<p>&nbsp;</p>\r\n<p>Your order number: <strong>{[order.number]}</strong>&nbsp; has been canceled.</p>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Sent date: <strong>{[time]}</strong></p>\r\n<p>&nbsp;</p>\r\n<p>url:{[site.siteurl]}<br /></p>\r\n<p>Email:{[site.email]}</p>\";s:4:\"type\";s:4:\"html\";}');
  ");
}

function update_0005()
{
  global $db;
  $db->exec("ALTER TABLE `products` ADD COLUMN `videopath` VARCHAR(255) DEFAULT '' NULL AFTER `filepath`;");
}

function update_0006(){
  global $db;
  $result = $db->query("SHOW COLUMNS FROM `articles_type` WHERE `Field` = 'pvid'");
  if(! $result->one()){
  	$db->exec("ALTER TABLE `articles_type` ADD COLUMN `pvid` INT(10) UNSIGNED DEFAULT '0' NOT NULL AFTER `path_alias`;");
  }
}

function update_0007(){
	global $db;
	$db->exec("ALTER TABLE `articles` ADD COLUMN `author` VARCHAR(20) NOT NULL AFTER `content`, ADD COLUMN `source` VARCHAR(50) NOT NULL AFTER `author`;");
}
function update_0008(){
	global $db;
	$wgcomp = Bl_Config::get('widget.productcompare');
	if($wgcomp['status']) {
		$db->select('p.directory_tid1,p.directory_tid2,wdc.id');
		$db->join('products p', 'p.pid=wdc.pid1');
		$result = $db->get('widget_compare wdc');
	  $wcomparelist = $result->all();
	  foreach ($wcomparelist as $wcp) {
	  	$tid = $wcp->directory_tid2 ? $wcp->directory_tid2 : ($wcp->directory_tid1 ? $wcp->directory_tid2 : 0);
	  	$db->exec('update widget_compare set directory_tid = ' . $tid . ' where id = ' . $wcp->id);
	  }
	}
}

