<?php
class Sphinx_Controller extends Bl_Controller
{
  public function init()
  {
    $this->_displayInfo = Bl_Config::get('display', array());
  }

  public function getAction($keyword = '', $page = 1,  $directoryTid = null)
  {
    if ($this->isPost()) {
      $keyword = isset($_REQUEST['keyword']) ? str_replace(' ', '-', $_REQUEST['keyword']) : '';
      $url = 'pitems/' . $keyword;
      //$url = 'search/' . $keyword;
      $directoryTid = isset($_REQUEST['directoryTid']) ? $_REQUEST['directoryTid'] : 0;
      if (isset($directoryTid) && $directoryTid) {
        $url = '/' . $page . '/' . $directoryTid;
      }
      gotourl($url);
    }
    $keyword = htmlentities($keyword, ENT_COMPAT, 'UTF-8');
    $keyword = trim(str_replace('-', ' ', $keyword));

    if ($keyword == '') {
      gotoUrl('');
    }
//    //获取子类
//    if (isset($directoryTid) && $directoryTid) {
//      $taxonomyInstance = Taxonomy_Model::getInstance();
//      $directoryTid = $taxonomyInstance->getTermChildsToLinearArray($directoryTid);
//    }

    // 调用搜索钩子
    widgetCallFunctionAll('search', $this, $keyword);

    $page = isset($page) ? $page : 1;

    $page = intval($page);
    $pageRows = isset($_SESSION['browseListConfig']['pageRows']) ? $_SESSION['browseListConfig']['pageRows'] : (key_exists('goodsListNum', $this->_displayInfo) ? $this->_displayInfo['goodsListNum'] : 24);
    
    if($pageRows == 'all')
      $pageRows = 96;
    
    $pageRows = intval($pageRows);
    $pageRows = 6;
    $listMode = isset($_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo';
    $orderby = isset($_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null;

    $sphinxServer = Bl_Config::get('sphinx.server', false);
    $sphinxPort = Bl_Config::get('sphinx.port', false);
    $sphinxIndex = Bl_Config::get('sphinx.index', false);
    if (!$sphinxServer || !$sphinxPort || !$sphinxIndex) {
      goto404(t('Sphinx setting is invalid'));
    }

    $sphinxInstance = Sphinx_Model::getInstance();
    $sphinxInstance->init($sphinxServer, $sphinxPort);
    $result = $sphinxInstance->searchProducts($sphinxIndex, $keyword, $page, $pageRows, $orderby, $directoryTid);
    $canShowMore = $result['showmore'];
    $result = $result['data'];
    if($result){
    	$productCount = $result['total_found'];
    	$productCount = $productCount > 1000 ? 1000 : $productCount;
    	$time = $result['time'];
    	$productList = array();
    	if (isset($result['matches']) && !empty($result['matches'])) {
    		$pids = array_keys($result['matches']);
    		log::save('debug', 'pids', $pids);
    		$productInstance = Product_Model::getInstance();
    		foreach ($pids as $pid) {
    			$productList[] = $productInstance->getProductInfo($pid);
    		}
    	}
    	$pageCount = ceil($productCount/$pageRows);
    	
    	$productInfo = array('list' => $productList, 'count' => $productCount);
    	$productInfo = (object) $productInfo;
    	
    	$lfilter['tids'] = widgetCallFunction('seotags', 'getTermIDByKeyword',$keyword);
    	callFunction('listproduct', $lfilter, $productInfo);
    	
    	$productList = $productInfo->list;
    	$productCount = $productInfo->count;
    	
    	foreach ($productList as $product){
    		//added by pzzhang to support direct purchase.
    		$type = $product->type;
    		if ($productInstance->checkTypeExist($type)) {
    			$product->type = $productInstance->getTypeInfo($type);
    			$product->fields = $productInstance->getTypeFieldsList($type);
    		} else {
    			$product->type = null;
    			$product->fields = array();
    		}
    	}
    	
    	
    	$getrandomfilter = array(
    			'keywords' => $keyword
    	);
    	callFunction('getrandom', 'search', $this,  $getrandomfilter);
    	
    	if ($page == 1) {
    		$_SESSION['FirstPath']['search'] = trim($_SERVER['REQUEST_URI'], '/');
    	}
    	$firstPath = isset($_SESSION['FirstPath']['search']) ? $_SESSION['FirstPath']['search'] : null;
    	$urlPage = '/pitems/' . strtr($keyword, array(' ' => '-')) . '/%d/';
    	
    	$breadcrumb = array();
    	$breadcrumb[] = array(
    			'title' => isset($this->_displayInfo['productListHomeName']) && $this->_displayInfo['productListHomeName'] ? $this->_displayInfo['productListHomeName'] : 'Home',
    			'path' => '',
    	);
    	
    	$breadcrumb[] = array(
    			'title' => 'Product Items',
    	);
    	$breadcrumb[] = array(
    			'title' => str_replace(' ', '-', $keyword),
    			'path' => 'pitems/'. str_replace(' ', '-', $keyword),
    	);
    	//     setBreadcrumb($breadcrumb);
    	
    	$pageInfo = widgetCallFunction('seotags', 'getPageVarible', $keyword);
    	if (isset($pageInfo) && $pageInfo && $pageInfo->title !== '') {
    		$this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
    	} else {
    		$frontInstance = Front_Model::getInstance();
    		$frontInstance->getPageVariableByKey($this, 'search', false, $keyword);
    	}
    	
    	/*<< SEO 需求临时处理*/
    	$seotagsConfig = Bl_Config::get('widget.seotags', 0);
    	if ($seotagsConfig && $seotagsConfig['status']) {
    		$tagInfo = widgetCallFunction('seotags', 'getTagId', $keyword, 'tag_id, ptag_id, status');
    		if ($tagInfo->status == 0) {
    			$metas = '<meta name="robots" content="noindex,nofollow" />' . PHP_EOL;
    			$this->view->assign('metas', $metas);
    		}
    	}
    	/*SEO 需求临时处理>>*/
    	
    	
    }
    
    $orderbystr = 'default';
    if($_SESSION['browseListConfig']['orderby'] == 'sell_price ASC, pid DESC'){
    	$orderbystr = 'low price';
    }else if($_SESSION['browseListConfig']['orderby'] == 'sell_price DESC, pid DESC'){
    	$orderbystr = 'high price';
    }else{
    	$orderbystr = 'default';
    }
    
    $this->view->render('search.phtml', array(
      'keyword' => $keyword,
//       'searchTime' => $time,
      'productList' => isset($productList) ? $productList : null,
//       'listMode' => $listMode,
      'page' => $page,
//       'pageRows' => $pageRows,
//       'orderby' => $orderby,
//       'pageCount' => $pageCount,
      'productCount' => isset($productCount) ? $productCount: 0,
//       'urlPage' => $urlPage,
      //be notice, here the second parameter $pageCount is different from the other pagination functions.
//       'pagination' => callFunction('common_pagination', $urlPage, $pageCount, $page, $firstPath), 
//       'pagination' => callFunction('combo_pagination', $urlPage, $pageCount, $page),
      'orderbystr' => $orderbystr,
      'canShowMore' => $canShowMore,
    ));
  }

	  public function skipAction()
	  {
	    $reffer_url = ltrim($_SERVER["HTTP_REFERER"], "/");
	    $pathinfo = pathinfo($reffer_url);
	    $path_alias_arr = explode('_', $pathinfo['filename']);
	    $path_alias = $path_alias_arr[0];
	    if ($this->isPost()) {
	      $post = $_POST;
	      if (!$post) {
	        gotoUrl('');
	      } else {
	        $keyword= isset($post['keyword']) && $post['keyword'] ? $post['keyword'] : 'no';
	
	        $_SESSION['browseListConfig']['pageRows'] = (isset($post['pageRows']) && $post['pageRows']) ? $post['pageRows'] : (
	          ((isset($_SESSION['browseListConfig']['pageRows']) && $_SESSION['browseListConfig']['pageRows']) ? $_SESSION['browseListConfig']['pageRows'] : ($this->_displayInfo['goodsListNum'] ? $this->_displayInfo['goodsListNum'] : 24))
	          );
	        $_SESSION['browseListConfig']['listMode'] = (isset($post['listMode']) && $post['listMode']) ? $post['listMode'] : (
	          ((isset($_SESSION['browseListConfig']['listMode']) && $_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo')
	          );
	        $_SESSION['browseListConfig']['orderby'] = (isset($post['orderby']) && $post['orderby']) ? $post['orderby'] : (
	          ((isset($_SESSION['browseListConfig']['orderby']) && $_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null)
	          );
	
// 	        $page = isset($post['page']) && $post['page'] ? $post['page'] : 1;
// 	        $url = 'pitems/' . strtr($keyword, array(' ' => '-')) . '/' . $page;
	        $url = 'pitems/' . strtr($keyword, array(' ' => '-')) ;
	        gotoUrl($url);
	      }
	    } else {
	      gotoUrl('');
	    }
	  }

	public function ajaxshowproductsAction(){
	    if ($this->isPost()) {
	    	$reffer_url = rtrim($_SERVER["HTTP_REFERER"], "/");
	    	$keyword = substr($reffer_url, strrpos($reffer_url, '/') + 1);
	    	$post = $_POST;
	    	$keyword = trim(str_replace('-', ' ', $keyword));
	    	if ($keyword == '') {
	    		gotoUrl('browse/all.html');
	    	}
		    $directoryTid = isset($_REQUEST['directoryTid']) ? $_REQUEST['directoryTid'] : 0;
		    // 调用搜索钩子
		    widgetCallFunctionAll('search', $this, $keyword);
		
		    $page = $post['page'];
		    $pageRows = 6;
		    
		    $listMode = isset($_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo';
		    $orderby = isset($_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null;
		
		    $sphinxServer = Bl_Config::get('sphinx.server', false);
		    $sphinxPort = Bl_Config::get('sphinx.port', false);
		    $sphinxIndex = Bl_Config::get('sphinx.index', false);
		    if (!$sphinxServer || !$sphinxPort || !$sphinxIndex) {
		      goto404(t('Sphinx setting is invalid'));
		    }
		
		    $sphinxInstance = Sphinx_Model::getInstance();
		    $sphinxInstance->init($sphinxServer, $sphinxPort);
		    $result = $sphinxInstance->searchProducts($sphinxIndex, $keyword, $page, $pageRows, $orderby, $directoryTid);
		    $canShowMore = $result['showmore'];
		    $result = $result['data'];
		    if($result){
		    	$productCount = $result['total_found'];
		    	$productCount = $productCount > 1000 ? 1000 : $productCount;
		    	$time = $result['time'];
		    	$productList = array();
		    	if (isset($result['matches']) && !empty($result['matches'])) {
		    		$pids = array_keys($result['matches']);
		    		$productInstance = Product_Model::getInstance();
		    		foreach ($pids as $pid) {
		    			$productList[] = $productInstance->getProductInfo($pid);
		    		}
		    	}
		    	$pageCount = ceil($productCount/$pageRows);
		    	
		    	$productInfo = array('list' => $productList, 'count' => $productCount);
		    	$productInfo = (object) $productInfo;
		    	
		    	$lfilter['tids'] = widgetCallFunction('seotags', 'getTermIDByKeyword',$keyword);
		    	callFunction('listproduct', $lfilter, $productInfo);
		    	
		    	$productList = $productInfo->list;
		    	$productCount = $productInfo->count;
		    	
		    	foreach ($productList as $product){
		    		//added by pzzhang to support direct purchase.
		    		$type = $product->type;
		    		if ($productInstance->checkTypeExist($type)) {
		    			$product->type = $productInstance->getTypeInfo($type);
		    			$product->fields = $productInstance->getTypeFieldsList($type);
		    		} else {
		    			$product->type = null;
		    			$product->fields = array();
		    		}
		    	}
		    	
		    	$getrandomfilter = array(
		    			'keywords' => $keyword
		    	);
		    	callFunction('getrandom', 'search', $this,  $getrandomfilter);
		    	
		    	if ($page == 1) {
		    		$_SESSION['FirstPath']['search'] = trim($_SERVER['REQUEST_URI'], '/');
		    	}
		    	$firstPath = isset($_SESSION['FirstPath']['search']) ? $_SESSION['FirstPath']['search'] : null;
		    	$urlPage = 'pitems/' . strtr($keyword, array(' ' => '-')) . '/%d/';

		    	$this->view->render('ajax/ajaxsearchproductlist.phtml', array(
		    			'keyword' => $keyword,
		    			'productList' => isset($productList) ? $productList : null,
		    			'productCount' => $productCount,
		    			'canShowMore' => $canShowMore,
		    	));
		    }
	    }
	}
}
