<?php
//公共钩子
function hook_page($instance){
	
	
   $productInstance = Product_Model::getInstance();
   $frontInstance = Front_Model::getInstance();
   $siteInstance = Site_Model::getInstance();

   //购物车中商品数量 和 总价
   $Temp_carts = $frontInstance->getCartProductList();
   $goodsAmount = isset($Temp_carts->goods_amount)? $Temp_carts->goods_amount : 0;
   $goodsInCart = isset($Temp_carts->goods_number)? $Temp_carts->goods_number : 0;
   $instance->view->assign('goodsInCart', $goodsInCart);
   $instance->view->assign('goodsAmount', $goodsAmount);

   //站点信息
//    $siteInfo = $frontInstance->getSiteInfo();
//    $instance->view->assign('siteInfo', $siteInfo);

   //取商品属性
   //$fieldsList = $productInstance->getProductFieldsIndex('handbags');
   //$instance->view->assign('fieldsList', isset($fieldsList) ? $fieldsList : null);

//   //使用的贷币列表
//   $currencyList = $siteInstance->getCurrenciesList();
//    $instance->view->assign('currencyList', $currencyList);

//   //公司联系方式
//   $contactway = $frontInstance->getContactWay();
//   $instance->view->assign('contactway', $contactway);

//    //产品分类
//   $termsList = $frontInstance->getTermsList();
//   $instance->view->assign('category', isset($termsList) ? $termsList : array());

//   //产品品牌
//   $brands = $frontInstance->getBrandsList();
//   $instance->view->assign('brands', isset($brands)?$brands : array());
  
//   //产品标签
//   $tagsList = $frontInstance->getTagsList();
//   $instance->view->assign('tagsList', isset($tagsList)? $tagsList : array());
  
//   //TopSearch
//   $topsearch = widgetCallFunction('topsearch', 'getResult');
//   $instance->view->assign('topsearch', $topsearch);
  
//   if(isset($_SESSION['chattop'])){
//   	$instance->view->assign('chattop', $_SESSION['chattop']);
//   }
//   if(isset($_SESSION['chatleft'])){
//   	$instance->view->assign('chatleft', $_SESSION['chatleft']);
//   }
  
  $cartcount = Cart_Model::getInstance()->getCartCount();
  $instance->view->assign('cartcount', $cartcount);
  
  $wishcount = User_Model::getWishListCount();
  $instance->view->assign('wishcount', $wishcount);
  

  //Help列表
//   $contentInstance = Content_Model::getInstance();
//  $helpIds = $contentInstance->getArticleUnderType(4);
//  $helpList = array();
//  if(isset($helpIds) && $helpIds){
//    for($i=0;$i<count($helpIds)-1;$i++){
//      $contTypeInfo = $contentInstance->getArticleTypeInfo($helpIds[$i]);
//      $helpList[$i] = $contTypeInfo;
//      $contTypeArticleList = $contentInstance->getArticleList(array('atid'=>$helpIds[$i]),1,3);
//      $helpList[$i]->sub = (isset($contTypeArticleList) && $contTypeArticleList) ? $contTypeArticleList : null;
//    }
//  }
//  $instance->view->assign('helpList', $helpList);

  //文章列表
//   $news_list= $frontInstance->getArticleList(array('type_id'=>'news','status'=>1),1,6);
//   $instance->view->assign('news_list', $news_list);
  
  // 页面底部文章列表
//   $articleTypeList = $contentInstance->getArticleTypeList();
//   $articleTypeList2 = array();
//   $helpList = array();
//   foreach($articleTypeList as $index=>$type){
//   	$articleTypeList2[$type->name] = $type;
//   	$articleList = $frontInstance->getArticleList(array('type_id'=>$type->type_id,'status'=>1),1,6);
//   	$helpList[$type->name] = $articleList;
//   }

//   $instance->view->assign('helpList', $helpList);
//   $instance->view->assign('articleTypeList', $articleTypeList2);

  //随机评论
  /*
  $commentList = null;
  $commentList = widgetCallFunction('randominfo', 'getinfo', 'comments', array('status' => 1), 4);
  $instance->view->assign('commentList', $commentList);
  */
  
  
  //stotags链接索引
//   $seoLinks = array('A' => 'sitemap/products-search/a', 'B' => 'sitemap/products-search/b', 'C' => 'sitemap/products-search/c', 'D' => 'sitemap/products-search/d',
//     'E' => 'sitemap/products-search/e', 'F' => 'sitemap/products-search/f', 'G' => 'sitemap/products-search/g', 'H' => 'sitemap/products-search/h', 'I' => 'sitemap/products-search/i', 'J' => 'sitemap/products-search/j', 'K' => 'sitemap/products-search/k',
//     'L' => 'sitemap/products-search/l', 'M' => 'sitemap/products-search/m', 'N' => 'sitemap/products-search/n', 'O' => 'sitemap/products-search/o', 'P' => 'sitemap/products-search/p', 'Q' => 'sitemap/products-search/q', 'R' => 'sitemap/products-search/r',
//     'S' => 'sitemap/products-search/s', 'T' => 'sitemap/products-search/t', 'U' => 'sitemap/products-search/u', 'V' => 'sitemap/products-search/v', 'W' => 'sitemap/products-search/w', 'X' => 'sitemap/products-search/x', 'Y' => 'sitemap/products-search/y', 'Z' => 'sitemap/products-search/z',
//   );
//   $instance->view->assign('seoLinks',$seoLinks);
}

function hook_comment_insert(Comment_Controller $instance)
  {
  //exit;
    global $user;
    $commentModel = Comment_Model::getInstance();
    if ($instance->isPost()) {
      $post = $_POST;
      if (!$post['comment']) {
        exit('Review content should not be empty. ');
      }
    	if(isset($post['comment_token']) && $post['comment_token'] != $_SESSION['comment_token']) {
    	   
      	setMessage('Token error!', 'error');
      	gotoBack();
      }
      $post['comment'] = trim($post['comment']);
      $post['comment'] = strip_tags($post['comment']);
      if (!$post['subject']) {
        $post['subject'] = substr($post['comment'], 0, 50);
        $pos = strpos($post['subject'], "\n");
        if($pos !== false){
        	$post['subject'] = substr($post['subject'], 0, $pos);
        }
      }
      $post['comment'] = preg_replace("/\r\n(\r\n)*/", "</p><p>", $post['comment']);
      $post['comment'] = preg_replace("/\n(\n)*/", "</p><p>", $post['comment']);
      
      $post['comment'] = str_ireplace("\r\n", "</br>", $post['comment']);
      $post['comment'] = str_ireplace("\n", "</br>", $post['comment']);
      
      //upload files
      $fileInstance = File_Model::getInstance();
      $uploadedFileCount = count($_FILES['file']['name']);
      $photo_paths = array();
      for ($i = 0; $i < $uploadedFileCount; $i++)
      {
          if (empty($_FILES['file']['name'][$i]))
          {
              continue;
          }
          $file = $fileInstance->uploadFile('file', array(), $i);
          if ($file)
          {
              $photo_paths[] = $file->filepath;
          }
          else
          {
              setMessage('upload file error!', 'error');
              gotoBack();
          }
      }
      $uid = isset($user->uid) ? $user->uid : 0;
      $nickname = (isset($_POST['nickname']) && $_POST['nickname']) ? $_POST['nickname'] : (isset($user->nickname) ? $user->nickname : '');
      $productInstance = Product_Model::getInstance();
      if ($productInstance->getProductInfo($post['pid'])) {
        $status = Bl_Config::get('isNeedAudit', 1) == 1 ? 0 : 1;
        $cid = $commentModel->insertComment($uid, $post['subject'], $post['comment'], $photo_paths, $nickname, $status);
        if ($cid) {
          cache::remove('product-' . $post['pid']);
          $commentModel->insertProductComments($post['pid'],$cid);
          if (isset($post['rating'])) {
            $grade = $post['rating'];
            widgetCallFunction('fivestars', 'setstars', $post['pid'], $cid, $grade);
            cache::remove('productStart-' . $post['pid']);
          }
        }
        $reffer_url = $_SERVER["HTTP_REFERER"];
        $pid = $post['pid'];
        if(isset($post['referer']) && $post['referer']) {
          gotoUrl('product/viewreviews/'.$pid);
        } elseif (isset($reffer_url) && $reffer_url) {
          gotoUrl('product/viewreviews/'.$pid);
        } else {
          gotoUrl('product/viewreviews/'.$pid);
        }
      } else {
        //TODO
        exit('No goods');
      }
    }else{
    	goto404();
    }
  }

//首页扩展
function hook_default_index(Default_Controller $instance)
{
	$frontInstance = Front_Model::getInstance();
	//幻灯片
	$siteInsance = Site_Model::getInstance();
	$hdp = $siteInsance->getcarouselphotoList(1,20);
	$instance->view->assign('hdpArr', $hdp);

  	//获取首页  meta 信息和  var 信息
  	$frontInstance->getPageVariableByKey($instance, 'index');

   	//获取新品列表
    $PRODUCT_COUNT = 12;
  	$newProductListBySpecial = $frontInstance->getProductsListBySpecial(array('special_tid' =>3), 1, $PRODUCT_COUNT);
  	$remains = $PRODUCT_COUNT - count($newProductListBySpecial);
   	if ($remains > 0) {
  		$newProductList = $frontInstance->getProductsList(array(), 1, 24);
	   	foreach($newProductList as $k=>$v){
	   		if($remains == 0) break;
	   		$remains--;
	   		if(!key_exists($k, $newProductListBySpecial)){
	   			$newProductListBySpecial[$k]= $newProductList[$k];
	   		}
	   	}
   	}
   	if(isset($newProductListBySpecial) && $newProductListBySpecial){
    	foreach ($newProductListBySpecial as $hot){
        	$hot->star = widgetCallFunction('fivestars','getaveragestars',$hot->pid);
    	}
   	}
    $instance->view->assign('newProductList', $newProductListBySpecial);

//   	//页脚推荐产品列表
//   	$recommendProductList = $frontInstance->getProductsListBySpecial(array('special_tid' =>30), 1, $PRODUCT_COUNT);
//     	if(isset($recommendProductList) && $recommendProductList){
//       		foreach ($recommendProductList as $hot){
//         	$hot->star = widgetCallFunction('fivestars','getaveragestars',$hot->pid);
//      	}
//     }
//     $instance->view->assign('recommendProductList', $recommendProductList);
    
    $instance->view->assign('isHomeIndex', true);
	$instance->indexAction();
}

function hook_getProductList(Product_Controller $instance, $productList, $termInfo){
   $taxonomyInstance = Taxonomy_Model::getInstance();
   $productInstance = Product_Model::getInstance();
   foreach ($productList as $product){
      $tids = $productInstance->getProductTerms($product->pid);
      if(isset($tids) && $tids){
      	foreach ($tids as $tid){
      		$termInfo = $taxonomyInstance->getTermInfo($tid);
      		if($termInfo->vid ==8) {
      			$product->discount = $termInfo->name;
      			break;
      		}
      	}
     }
    
     //added by pzzhang to support direct purchase.
     $productInstance->getProductTypeAndTypeField($product);
     $type = $product->type;
     $product->type = $productInstance->getTypeInfo($type);
     $product->fields = $productInstance->getTypeFieldsList($type);
    }
  return $productList;
};

// function hook_product_browse($instance, $url = '' , $params = null, $page = 1){
//   $s = microtime(1);
//   $instance->browseAction($url , $params, $page);
// }

function hook_getrandom($op, $instance, $filter)
{
  if ($op == 'browse') {
    $filter['directory_tid'] = $filter['tid'];
    $filter['level'] = 'self';
    $similarproduct = widgetCallFunction('randominfo', 'getinfo', 'products', $filter, 9);
  } elseif ($op == 'product') {
    $filter['directory_tid'] = $filter['tid'];
    $filter['level'] = 'self';
    $similarproduct = widgetCallFunction('randominfo', 'getinfo', 'products', $filter, 9);
    $frontInstance = Front_Model::getInstance();
    $_SESSION['comment_token'] = $productCommentToken = randomString(16);
    $instance->view->assign('comment_token', $productCommentToken);
  }
  $instance->view->assign('recommandProductList', $similarproduct);
}

function product_info($instance, $product){
  $frontInstance = Front_Model::getInstance();
  $productInstance = Product_Model::getInstance();
  $commentInstance = Comment_Model::getInstance();

  //TODO: Add the recently feature in the future with simplified data structure.
  
  /*$_SESSION['recently'] = isset($_SESSION['recently']) ? $_SESSION['recently'] : array();
  $_SESSION['recently'][$product->pid] = $product;
  $_SESSION['recently'] = array_slice($_SESSION['recently'], 0, 5, true);
  $recently = $_SESSION['recently'];
  $instance->view->assign('recently', $recently);*/
  
  /*
  $commentList = null;
  $commentCount = 0;
  if (!isSearchEngine()) {
  	$commentList = $frontInstance->getCommentsListByProductId($product->pid, $filter = array('status' => 1), 1, null);
  	$commentCount = $commentInstance->getCommentsCountByProductId($product->pid, $filter = array('status' => 1));
  
  	foreach($commentList as $k=>$v){
    	$v->children = $commentInstance->getReplayInfo($v->cid);
  	};
  }
  $instance->view->assign('commentCount', $commentCount);
  $instance->view->assign('commentList', $commentList);
  */
  $commentCount = $commentInstance->getCommentsCountByProductId($product->pid, $filter = array('status' => 1));
  $instance->view->assign('commentCount', $commentCount);
  
  $similarproduct2 = widgetCallFunction('randominfo', 'getinfo', 'products', array('directory_tid' => $product->directory_tid), 40);
  $instance->view->assign('similarproduct2', $similarproduct2);

  $similarproduct3 = widgetCallFunction('randominfo', 'getinfo', 'products_2', array('directory_tid' => $product->directory_tid), 5);
  $instance->view->assign('similarproduct3', $similarproduct3);

  $similararticles = widgetCallFunction('randominfo', 'getinfo', 'articles', null, 10);
  $instance->view->assign('similararticles', $similararticles);

}  

//商品显示页面
function hook_product_term(Product_Controller $instance, $tid, $path)
{
  $path = basename($path, '.html');
  $productInstance = Product_Model::getInstance();
  if (!$product = $productInstance->getProductInfoByPathAlias($path)) {
    goto404('Product path alias <em>' . $path . '.html</em> not found.');
  }
  product_info($instance, $product);
  //获取商品评论列表
  $path = $path . '.html';
  $instance->termAction($tid, $path);
}

//商品显示页面
function hook_product_view(Product_Controller $instance, $pid){
	$productInstance = Product_Model::getInstance();
  if (!$product = $productInstance->getProductInfo($pid)){
    goto404('Product <em>' . $pid . '</em> not found.');
  }
//   product_info($instance, $product);
  $temp = $_SESSION;
  $instance->viewAction($pid);
}


//留言入库
function hook_guestbook_myadd($instance)
{
  $frontInstance = Front_Model::getInstance();
if ($instance->isPost()) {
      $post = $_POST;
      if (isset($post['data']) && $post['data']) {
        $post['data'] = serialize($post['data']);
      }
      if ($frontInstance->insertWebsiteMessage($post)) {
        setMessage('Thank you very much for your feedback');
      } else {
        setMessage('Sorry, the operation failed');
      }
    }
    gotoUrl('contactus.html');
}

function hook_product_paymentc(Product_Controller $instance, $value = 0)
{
  echo c($value);
  exit;
}

  /*发送下单邮件*/
function hook_cart_sendmail (Cart_Controller $instance, $oid)
{
    $orderInstance = Order_Model::getInstance();
    $orderInfo = $orderInstance->getOrderInfo($oid);
    if(count($orderInfo)>0){
      $emailSetting = Bl_Config::get('orderTradingEmail');
      $stmpSetting = Bl_Config::get('stmp');
      $email = $orderInfo->delivery_email;

      if (isset($stmpSetting) && $stmpSetting['stmpserver'] && $stmpSetting['stmpuser'] && $stmpSetting['stmppasswd'] && $email) {
        $mailInstance = new Mail_Model($stmpSetting);
        //$email = isset($user->email) ? $user->email : null;
        if ($mailInstance->sendMail($email, $emailSetting['title'], $emailSetting['content'], $emailSetting['type'])) {
          setMessage('Sending order email successfully.');
        } else {
            setMessage('Encounter error when sending email.', 'error');
          }
		} else {
			setMessage('Mail server information is not configured properly, please check', 'error');
      }
    }
}
//构建新的会员首页
function hook_user_home($instance){

  $orderInstance = Order_Model::getInstance();
  global $user;
  $instance->view->assign('user', $user);
    $userInstance = User_Model::getInstance();
    if (!$userInstance->logged()) {
      gotoUrl('user/login');
    }
    $filter['uid'] = $user->uid;
    $filter['status'] = 0;
     $ordersList = $orderInstance->getOrdersList($filter, 1, 5);
    foreach ($ordersList as $k => $v) {
      $ordersItems =  $orderInstance->getOrderItems($v->oid);
      $ordersItems = array_splice($ordersItems, 0, 1);
      $ordersList[$k]->firstitem = isset($ordersItems[0]) ? $ordersItems[0] : null;
    }
    $orderscount = $orderInstance->getOrdersCount($filter);

    $instance->view->assign('orderscount', $orderscount);
    $instance->view->assign('templatefile', 'u_userindex.phtml');
    $instance->view->addCss(url('styles/themes/base/jquery-ui-1.8.19.custom.css'));

   $instance->view->render('personalcenter.phtml', array(
      'ordersList' => $ordersList,
    ));
}

function hook_payment_fail(Payment_Controller $instance, $payment) {
	gotoUrl('/');
}	

function hook_product_custommeasurements(Product_Controller $instance, $pid){
	$instance->custommeasurementsAction($pid);
}

function hook_user_newaddress($instance){
	$sourceurl = $_SERVER;
	$_SESSION['newaddressfrom'] = $_SERVER['HTTP_REFERER'];
	$instance->newaddressAction();
}
