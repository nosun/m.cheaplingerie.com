<?php
class Product_Controller extends Bl_Controller
{
    private $_productInstance;

    public static function __router($paths)
    {
        if (!isset($paths[0])) {
            goto404(t('Argument 0 is invalid'));
        }
        if (preg_match('/^([\w-]+)\.html$/i', $paths[0], $matches)) {
            $paths[0] = $matches[1];
            return array(
        'action' => 'pathalias',
        'arguments' => $paths,
            );
        } else if (preg_match('/^\d+$/', $paths[0])) {
            return array(
        'action' => 'view',
        'arguments' => $paths,
            );
        }
    }

    public function init()
    {
        $this->_productInstance = Product_Model::getInstance();
        $this->_displayInfo = Bl_Config::get('display', array());
    }

    public function indexAction()
    {
        gotourl('product/list');
    }

    public function termAction($tid, $path){
        $path = basename($path, '.html');
        $productInfo = $this->_productInstance->getProductInfoByPathAlias($path);
        $this->_view($productInfo);
    }

    //added for seo optimization.
    public function seoAction($path){
        $path = basename($path, '.html');
        $productInfo = $this->_productInstance->getProductInfoByPathAlias($path);
        $this->_view($productInfo);
    }

    public function viewAction($pid)
    {
        if (!$product = $this->_productInstance->getProductInfo($pid)) {
            goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
        }

        if($this->isPost() && isset($_POST['custom']) ){
        	$post = $_POST;
        	array_shift($post);
        	$_SESSION['custom'] = $post;
        	unset($_SESSION['size']);
        	unset($_SESSION['backtoproperty']);
        }
        //其他post回来的分支，记得带个数据类型的标记
        else{
        	if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']){
        	
        		$maintainarray = array(
        				'product/viewsizechart',
        				'product/viewcolorchart',
//         				'product/selectproperty',
        				'product/custommeasurements',
//         				'product/viewdescription',
        				'product/viewreviews',
        				// 其他的不清session的get来源url格式放在这里
        		);
        	
        		$sourceurl = $_SERVER['HTTP_REFERER'];
        		if(strpos($sourceurl, 'product/viewsizechart') || strpos($sourceurl, 'product/custommeasurements')){
        			$_SESSION['backtoproperty'] = "size";
        		}
        		elseif(strpos($sourceurl, 'product/viewcolorchart')){
        			$_SESSION['backtoproperty'] = "color";
        		}
        		$clearsessionflag = true;
        		foreach($maintainarray as $url){
        			if(strpos($sourceurl, $url)){
        				$clearsessionflag = false;
        				break;
        			}
        		}
        		if($clearsessionflag){
        			unset($_SESSION['color']);
	        		unset($_SESSION['size']);
	        		unset($_SESSION['beltcolor']);
	        		unset($_SESSION['custom']);
	        		unset($_SESSION['backtoproperty']);
	        		unset($_SESSION['quantity']);
        		}
        	}
        	else{
        		unset($_SESSION['color']);
        		unset($_SESSION['size']);
        		unset($_SESSION['beltcolor']);
        		unset($_SESSION['custom']);
        		unset($_SESSION['backtoproperty']);
        		unset($_SESSION['quantity']);
        	}
        }
        $this->_view($product);
    }

    public function pathaliasAction($path)
    {
        if (!$product = $this->_productInstance->getProductInfoByPathAlias($path)) {
            goto404(t('Product path alias') . '<em>' . $path . '.html</em>' . t('not found.'));
        }
        $this->_view($product);
    }

    private function _view($product)
    {
        $config = Bl_Config::get('undercarriageShow');
        if (!$config && !$product->status) {
            goto404(t('Product path alias') . '<em>' . $path . '.html</em>' . t('not found.'));
        }
        $pid = $product->pid;
        $this->_productInstance->addProductVisit($pid);

        $product->summary = strtr($product->summary, array('&nbsp;' => ' '));
        $product->description = strtr($product->description, array('&nbsp;' => ' '));

        $product->files = array_values($this->_productInstance->getProductFilesList($pid));

        
        $type = $product->type;

		if ($this->_productInstance->checkTypeExist($type)) {
        	$product->type = $this->_productInstance->getTypeInfo($type);
        	$product->fields = $this->_productInstance->getTypeFieldsList($type);
        } else {
        	$product->type = null;
        	$product->fields = array();

        }
        $breadcrumb[] = array(
		      'title' => key_exists('productListHomeName', $this->_displayInfo) ? $this->_displayInfo['productListHomeName'] : 'Home',
		      'path' => '',
        );
        
        $taxonomyInstance = Taxonomy_Model::getInstance();
        if ($termInfo = $taxonomyInstance->getTermInfo($product->directory_tid)) {

            if (!$termInfo->ptid1) {
                $product->directorytid[] = $termInfo->tid;
            } else if (!$termInfo->ptid2) {
                $product->directorytid[] = $termInfo->ptid1;
                $product->directorytid[] = $termInfo->tid;
            } else if (!$termInfo->ptid3) {
                $product->directorytid[] = $termInfo->ptid1;
                $product->directorytid[] = $termInfo->ptid2;
                $product->directorytid[] = $termInfo->tid;
            } else {
                $product->directorytid[] = $termInfo->ptid1;
                $product->directorytid[] = $termInfo->ptid2;
                $product->directorytid[] = $termInfo->ptid3;
                $product->directorytid[] = $termInfo->tid;
            }
            if ($product->directorytid) {
                $termParents = $product->directorytid ? $product->directorytid : array();
                foreach ($termParents as $tid) {
                    $term = $taxonomyInstance->getTermInfo($tid);
                    $product->directory[] = $term;
                    $breadcrumb[] = array(
			            'title' => $term->name,
			            'path' => $term->path_alias . '.html',
                    );
                }
                $product->directory = array_reverse($product->directory);
            }
        } else {
            $product->directory = null;
        }
        if ($brand = $taxonomyInstance->getTermInfo($product->brand_tid)) {
            $product->brand = $brand;
        } else {
            $product->brand = null;
        }

        $breadcrumb[] = array(
      		'title' => $product->name,
        );
        setBreadcrumb($breadcrumb);        
        
        $pageInstance = PageVariable_Model::getInstance();
        if (isset($product->pvid)) {
            $pageInfo = $pageInstance->selectPageVariables($product->pvid, 'product', $product);
        }
        if (isset($pageInfo) && $pageInfo) {
            $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description , $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
        }
        
        if($product->fid){
            $product->bigfilepath = $product->filepath;
        }
        else {
        	$product->bigfilepath = isset($product->files[0]->filepath) ? $product->files[0]->filepath : '';
        }
        
        $getrandomfilter = array(
        	'tid' => $product->directory_tid,
        	'keyword' => $product->tname
        );
        
        callFunction('getrandom', 'product', $this,  $getrandomfilter);
        
        global $user;
        $canComment = User_Model::getInstance()->validateUserPurchaseProduct($user->uid, $product->pid);
        $isAddToWishList = User_Model::getInstance()->isAddToWishList($product->pid);

        $grades = widgetCallFunction('fivestars', 'getStars', $product->pid);
        $gradesTotal = 0;
        $sumTotal = 0;
        $fullcount = 0;
        $showstar = false;
        if(isset($grades)){
        	foreach($grades as $k=> $v){
        		$gradesTotal += $k * $v;
        		$sumTotal += $v;
        	}
        	if($sumTotal > 0){
        		$avggrades = round($gradesTotal/$sumTotal, 2);
        		$fullcount = intval($avggrades);
        		if(strpos($avggrades, '.')){
        			$halftemp = explode(".", $avggrades);
        			if($halftemp[1] >= 0.5){
        				$fullcount += 0.5;
        			}
        		}
        		$showstar = true;
        	}
        }
        
        $commentInstance = Comment_Model::getInstance();
        $comments = $commentInstance->getCommentsListByProductId($product->pid,array(), 1, 2);
        
        $this->view->render("product.phtml", array(
        	'product' => $product,
        	'canComment' => $canComment,
        	'comments' => $comments,
        	'isaddtowishlist' => $isAddToWishList,
        	'color' => isset($_SESSION['color']) ? $_SESSION['color'] : null,
        	'size' => isset($_SESSION['size']) ? $_SESSION['size'] : null,
        	'beltcolor' => isset($_SESSION['beltcolor']) ? $_SESSION['beltcolor'] : null,
        	'custom' => isset($_SESSION['custom']) ? $_SESSION['custom'] : null,
        	'pcount' => isset($_SESSION['quantity']) ? $_SESSION['quantity'] : null,
        	'bakctoproperty' => isset($_SESSION['backtoproperty'])? $_SESSION['backtoproperty'] : null,
        	'showstar' => $showstar,
        	'star' => $fullcount,
        	'count' => $sumTotal,
        ));
    }

    public function ajaxGetCommentAction($pid) {
    	$frontInstance = Front_Model::getInstance();
    	$commentInstance = Comment_Model::getInstance();
    	
    	$commentList = $frontInstance->getCommentsListByProductId($pid, $filter = array('status' => 1), 1, null);
    	$commentCount = $commentInstance->getCommentsCountByProductId($pid, $filter = array('status' => 1));
    	
    	foreach($commentList as $k=>$v){
    		$v->children = $commentInstance->getReplayInfo($v->cid);
    	};
    	global $user;
    	$canComment = User_Model::getInstance()->validateUserPurchaseProduct($user->uid, $pid);
    	echo $this->view->render('comments/review_list.phtml', array('commentCount' => $commentCount,
    			'commentList' => $commentList, 
    			'pid' => $pid, 
    			'user'=> $user,
    			'canComment' => $canComment
    	));
    }
    
    public function ajaxGetMostCommentedProductsAction($tid) {
    	$termInfo = Taxonomy_Model::getInstance()->getTermInfo($tid);
    	$mostCommentedProducts = $this->getMostCommentedProducts($termInfo);
    	
    	echo $this->view->render('comments/directory_review_list.phtml', array('mostCommentedProducts' => $mostCommentedProducts,));
    }
    
    public function listAction($page = 1)
    {
        $listMode = isset($_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo';
        $orderby = isset($_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null;
        $pageRows = isset($_SESSION['browseListConfig']['pageRows']) ? $_SESSION['browseListConfig']['pageRows'] : (key_exists('goodsListNum', $this->_displayInfo) ? $this->_displayInfo['goodsListNum'] : 24);
        $productCount = $this->_productInstance->getProductsCount(array());
        	
        $pageCount = 0;
        $eachPage = 0;

        if($pageRows == 'all' || $pageRows <= 0){
            $pageCount = 1;
            $eachPage = $productCount;
        }
        else {
            $eachPage = $pageRows;
            $pageCount = ceil($productCount/$pageRows);
        }

        $productList = $this->_productInstance->getProductsList(array('orderby' => $orderby), $page, $eachPage);

        	
        foreach ($productList as $product){
            //added by pzzhang to support direct purchase.
            $type = $product->type;
            if ($this->_productInstance->checkTypeExist($type)) {
                $product->type = $this->_productInstance->getTypeInfo($type);
                $product->fields = $this->_productInstance->getTypeFieldsList($type);
            } else {
                $product->type = null;
                $product->fields = array();
            }
        }

        $breadcrumb = array();
        $breadcrumb[] = array(
      'title' => key_exists('productListHomeName', $this->_displayInfo) ? $this->_displayInfo['productListHomeName'] : 'Home',
      'path' => '',
        );
        $breadcrumb[] = array(
      'title' => 'allgoods',
        );
        setBreadcrumb($breadcrumb);
        if ($page == 1) {
            $_SESSION['FirstPath']['productList'] = trim($_SERVER['REQUEST_URI'], '/');
        }
        $firstPath = isset($_SESSION['FirstPath']['productList']) ? $_SESSION['FirstPath']['productList'] : null;

        if(!isset($product->price)){
            $product->price = $product->sell_price;
        }

        $this->view->render('productlist.phtml', array(
      'productList' => isset($productList) ? $productList : null,
  	  'listMode' => $listMode,
  	  'page' => $page,
  	  'pageRows' => $pageRows,
      'orderby' => $orderby,
  	  'pageCount' => $pageCount,
  	  'productCount' => $productCount,
        //be notice, here the second parameter $pageCount is different from the other pagination functions.
      'pagination' => callFunction('common_pagination', 'product/list/%d/', $pageCount, $page, $firstPath), 
        ));
    }

    /**
     * 根据目录创建面包屑导航
     * @param unknown_type $termInfo
     * @param unknown_type $all 是否查看所有产品。
     */
    public function createBreadCrumbNav($termInfo, $all = false)
    {
        if ($all)
        {
            $breadcrumb[] = array(
     			'title' => 'all',
            );
        }
        else
        {
            $tid = null;
            if (isset($termInfo) && isset($termInfo->tid))
            {
                $tid = $termInfo->tid;
            }
            $breadcrumb_tid = (isset($tid) && $tid) ? $tid : 0;
            $breadcrumb = array();
            $breadcrumb[] = array(
         		'title' => key_exists('productListHomeName',$this->_displayInfo) ? $this->_displayInfo['productListHomeName'] : 'Home',
         		'path' => '',
            );
            $taxonomyInstance = Taxonomy_Model::getInstance();
            if ($termInfo = $taxonomyInstance->getTermInfo($breadcrumb_tid))
            {
                if (!$termInfo->ptid1)
                {
                    $termParents['directory_tid1'] = $termInfo->tid;
                } 
                else if (!$termInfo->ptid2)
                {
                    $termParents['directory_tid1'] = $termInfo->ptid1;
                    $termParents['directory_tid2'] = $termInfo->tid;
                }
                else if (!$termInfo->ptid3)
                {
                    $termParents['directory_tid1'] = $termInfo->ptid1;
                    $termParents['directory_tid2'] = $termInfo->ptid2;
                    $termParents['directory_tid3'] = $termInfo->tid;
                }
                else
                {
                    $termParents['directory_tid1'] = $termInfo->ptid1;
                    $termParents['directory_tid2'] = $termInfo->ptid2;
                    $termParents['directory_tid3'] = $termInfo->ptid3;
                    $termParents['directory_tid4'] = $termInfo->tid;
                }
                if ($termParents)
                {
                    foreach ($termParents as $tid)
                    {
                        $term = $taxonomyInstance->getTermInfo($tid);
                        $product->directory[] = $term;
                        if ($term->vid == 4)
                        {
                            $breadpath = '++';
                        }
                        elseif ($term->vid == 3)
                        {
                            $breadpath = '';
                        } 
                        elseif ($term->vid == 2)
                        {
                            $breadpath = '+';
                        }
                        $breadcrumb[] = array(
         					'title' => t($term->name),
         					'path' => isset($breadpath) ? ($breadpath. $term->path_alias . '.html') : categoryURL($term->path_alias),
                        );
                    }
                }
            }
        }
        setBreadcrumb($breadcrumb);
    }
    
    /**
     * 设置标题信息
     * @param $termInfo term分类信息
     */
    public function setPageTitle($termInfo)
    {
        $pageInstance = PageVariable_Model::getInstance();
        if(isset($termInfo->pvid))
        {
            $pageInfo = $pageInstance->selectPageVariables($termInfo->pvid, 'term', $termInfo);
        }
        else
        {
            $pageInfo = $pageInstance->getPageVariableByKey('allgoods');
        }
        if (isset($pageInfo) && $pageInfo)
        {
            $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
        }
    }
    
	private function parseBrowseUrlParams($url, &$page) {
		$taxonomyInstance = Taxonomy_Model::getInstance();
		$url = basename($url, '.html');
		list($directory_path_alias, $brand_path_alias, $tid_path_alias, $type, $fields, $prices, $page) = $urlArray = array_pad(explode('+', $url), 7, '');
		if (empty($page)) {
			$page = 1;
		}
		$urlPageFirst = $urlPage = $directory_path_alias . '+' . $brand_path_alias . '+' . $tid_path_alias . '+' . $type . '+' . $fields . '+' . $prices;
		$pvtitle = '';
		$relateName = array();
		if (isset($directory_path_alias) && $directory_path_alias && $directory_path_alias != 'all' && $directory_path_alias != 'allgoods') {
			$termInfo = $taxonomyInstance->getTermInfoByPathAlias($directory_path_alias);
			if (isset($termInfo) && $termInfo) {
				$directory_tid = isset($termInfo->tid) ? $termInfo->tid : null;

				$parent = $taxonomyInstance->getTermParents($termInfo->tid);
				$termInfo->parent = $parent;
				$pvid = $termInfo->pvid;
				$pvtitle .= str_replace('-', ' ', $directory_path_alias);
				$relateName[] =  $termInfo->name;
			} else {
				goto404(t('Can not found this Page'));
			}
		}

		if (isset($brand_path_alias) && $brand_path_alias) {
			$termInfo = $taxonomyInstance->getTermInfoByPathAlias($brand_path_alias);
			if (isset($termInfo) && $termInfo) {
				$brand_tid = isset($termInfo->tid) ? $termInfo->tid : null;
				$pvid = $termInfo->pvid;
				$pvtitle .= str_replace('-', ' ', $brand_path_alias);
				$relateName[] =  $termInfo->name;
			} else {
				goto404(t('Can not found this Page'));
			}
		}

		$tids = array();
		if (isset($tid_path_alias) && $tid_path_alias) {
			$tids_path_alias = explode('_', $tid_path_alias);
			foreach ($tids_path_alias as $path_alias) {
				$termInfo = $taxonomyInstance->getTermInfoByPathAlias($path_alias);
				if (isset($termInfo) && $termInfo) {
					$tids[] = $termInfo->tid;
					$pvid = $termInfo->pvid;
					$pvtitle .= str_replace('-', ' ', $path_alias);
					$relateName[] =  $termInfo->name;
				} else {
					goto404(t('Can not found this Page'));
				}
			}
		}
		
		$pageInstance = PageVariable_Model::getInstance();
		if(isset($pvid)){
			$pageInfo = $pageInstance->selectPageVariables($pvid, 'term', $termInfo);
		}else{
			$pageInfo = $pageInstance->getPageVariableByKey('allgoods');
		}
		if (isset($pageInfo) && $pageInfo) {
			$this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
		}

		if (isset($type) && $type) {
			$pvtitle .= ' ' . $type;
		}

		$fieldsarr = array();
		if (isset($fields) && $fields) {
			$fieldsarr1 = explode('_', $fields);
			foreach ($fieldsarr1 as $field) {
				$field = explode('=', $field);
				$pvtitle .= ' ' . $field[1];
				if (is_array($field) && $field && isset($field[0]) && isset($field[1])) {
					$filedsarr[$field[0]] = $field[1];
				}
			}
		}
		$pricesarr = array();
		if (isset($prices) && $prices) {
			$pricesarr = explode('_', $prices);
		}
		$orderby = isset($_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null;
		$filter = array(
			'directory_tid' => isset($directory_tid) ? $directory_tid : null,
			'brand_tid' => isset($brand_tid) ? $brand_tid : null,
			'tids' => isset($tids) ? $tids : null,
			'type' => isset($type) ? $type : null,
			'filedsarr' => isset($filedsarr) ? $filedsarr : null,
			'pricesarr' => isset($pricesarr) ? $pricesarr : null,
			'orderby' => isset($orderby) ? $orderby : null,
		);
		return $filter;
	}
    public function browseAction($url = '', $page = 1, $clear = null)
    {
        if ($url == '') {
			gotoUrl('browse/all.html');
		}
		if (!preg_match ("/.html$/i", $url)) {
			goto404(t('no page'));
		}
		$filter = $this->parseBrowseUrlParams($url, $page);
		$eachPage = 12;
        $productList = Product_Model::getInstance()->searchProductList($filter, $page , $eachPage);
		$productCount = Product_Model::getInstance()->searchProductCount($filter);
		$pageCount = ceil($productCount / $eachPage);
		$this->createBreadCrumbNav($termInfo);
        $orderby = 'default';
        if (!empty($_SESSION['browseListConfig']['orderby'])) {
	        if($_SESSION['browseListConfig']['orderby'] == 'sell_price ASC, pid DESC'){
	        	$orderby = 'low price';
	        }else if($_SESSION['browseListConfig']['orderby'] == 'sell_price DESC, pid DESC'){
	        	$orderby = 'high price';
	        }else{
	        	$orderby = 'default';
	        }
        }
        $this->view->render("productlist.phtml", array(
        		'termInfo' => isset($termInfo) ? $termInfo : null,
        		'productList' => isset($productList) ? $productList : null,
        		'lists' => count($termchilds) ? $termchilds : null,
        		'orderby' => $orderby,
				'page' => $page,
				'pageCount' => $pageCount,
        ));
    }
    
    private function getMostCommentedProducts($termInfo)
    {
        $commentInstance = Comment_Model::getInstance();
        $configure = Bl_Config::get('widgetFiveStartsStatus');
        $mostCommentedProducts = $this->_productInstance->getPopularCommentedProducts($termInfo, 10);
        foreach($mostCommentedProducts as $k=>$v){

            $v->url = ($v->path_alias !== '' ? $v->path_alias : $v->pid).'-p'.$v->sn . '.html';
            //$v->url = $termPathAlias . '/' . ($v->path_alias !== '' ? $v->path_alias : $v->pid) . '.html';
            if($configure === '1'){
                $commentsByGrade = widgetCallFunction('fivestars', 'getcommentsbygrade', $v->pid, null, 1);
                if($commentsByGrade && count($commentsByGrade) > 0){
                    $v->popularComment = $commentsByGrade[0];
                }else{
                    //get the comment generated recently
                    $commentList = $commentInstance->getCommentsListByProductId($v->pid, $filter = array('status' => 1, 'orderby'=> 'created DESC '));
                    if($commentList && count($commentList > 0)){
                        $v->popularComment = $commentList[0];
                        $v->popularComment->grade = 0;
                    }
                }
            }
        }
        return $mostCommentedProducts;
    }
    
    /**
     * 提供一组产品，返回产品用于过滤的类别
     * 产品用于过滤的类别为一组产品中占比最多的类别
     * @param unknown_type $productList
     */
    public function getFilterTypeByProductList($productList)
    {
        $typeList = array('all' => -1);
        $maxType = null;
        $maxCount = -1;
        foreach ($productList as $k => $v)
        {
            if (array_key_exists($v->type, $typeList))
            {
                $typeList[$v->type]++;
            }
            else
            {
                $typeList[$v->type] = 1;
            }
            if ($maxCount < $typeList[$v->type])
            {
                $maxCount = $typeList[$v->type];
                $maxType = $v->type;
            }
        }
        return $maxType;
    }
    
    public function skipAction()
    {
        if ($this->isPost()) {
            $post = $_POST;
            if (!$post) {
                gotoUrl('browse/all.html');
            } else {
                $_SESSION['browseListConfig']['listMode'] = (isset($post['listMode']) && $post['listMode']) ? $post['listMode'] : (
                ((isset($_SESSION['browseListConfig']['listMode']) && $_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo')
                );
                $_SESSION['browseListConfig']['orderby'] = (isset($post['orderby']) && $post['orderby']) ? $post['orderby'] : (
                ((isset($_SESSION['browseListConfig']['orderby']) && $_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null)
                );
                gotoBack();
            }
        } else {
            gotoUrl('');
        }
    }


    public function ajaxshowproductsAction(){
        if ($this->isPost()) {
            $post = $_POST;
            $reffer_url = ltrim($_SERVER["HTTP_REFERER"], "/");
            $uri = Bl_Core::getUri();
            if(endsWith($reffer_url, '.html')){
                //if not ends with .html, then we will not use
                Bl_Core::dynamicRouter($reffer_url);
                $url = basename($reffer_url, '.html');
                $url = $url . '.html';
                if ($url == '++++++1.html' || $url == 'all++++++1.html'){
                    $url = 'all.html';
                }
                $routers = Bl_Config::get('routers');
                if (isset($routers[$url]) && $routers[$url]) {
                    $url = $routers[$url];
                } else {
                    $url = basename($url, '.html');
                }
                $urls = explode('/', $url);
                reset($urls);
                $url = end($urls);
            }else{
                $url = '';
            }
            list($directory_path_alias, $brand_path_alias, $tid_path_alias, $type, $fields, $prices, $page) = $urlArray = array_pad(explode('+', $url), 7, '');
            $urlPageFirst = $directory_path_alias . '+' . $brand_path_alias . '+' . $tid_path_alias . '+' . $type . '+' . $fields . '+' . $prices;
            $urlPage = $urlPageFirst + '%d';
            $taxonomyInstance = Taxonomy_Model::getInstance();
            if (isset($directory_path_alias) && $directory_path_alias && $directory_path_alias != 'all' && $directory_path_alias != 'allgoods') {
                $termInfo = $taxonomyInstance->getTermInfoByPathAlias($directory_path_alias);
                if (isset($termInfo) && $termInfo) {
                    $directory_tid = isset($termInfo->tid) ? $termInfo->tid : null;
                }
            }
            if (isset($brand_path_alias) && $brand_path_alias) {
                $termInfo = $taxonomyInstance->getTermInfoByPathAlias($brand_path_alias);
                if (isset($termInfo) && $termInfo) {
                    $brand_tid = isset($termInfo->tid) ? $termInfo->tid : null;
                }
            }
            $tids = array();
            if (isset($tid_path_alias) && $tid_path_alias) {
                $tids_path_alias = explode('_', $tid_path_alias);
                foreach ($tids_path_alias as $path_alias) {
                    $termInfo = $taxonomyInstance->getTermInfoByPathAlias($path_alias);
                    if (isset($termInfo) && $termInfo) {
                        $tids[] = $termInfo->tid;
                    }
                }
            }

            $type = isset($post['type']) ? $post['type'] : $type;
            $fileds = isset($post['fileds']) ? implode('_', $post['fileds']) : $fields;
            $prices = (isset($post['lowprice']) ? $post['lowprice'] : '') . (isset($post['highprice']) ?  '_'  . $post['highprice'] : $prices);
            $pageRows = isset($post['pageRows']) && $post['pageRows'] ? $post['pageRows'] : 60;
            $orderby = isset($_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null;

            $firstPath = isset($_SESSION['FirstPath']['browse']) ? $_SESSION['FirstPath']['browse'] : null;

            $page = isset($page) && $page ? $page : 1;
            $filter = array(
		      'directory_tid' => isset($directory_tid) ? $directory_tid : null,
		      'brand_tid' => isset($brand_tid) ? $brand_tid : null,
		      'tids' => isset($tids) ? $tids : null,
		      'type' => isset($type) ? $type : null,
		      'filedsarr' => isset($fileds) ? $fileds : null,
		      'pricesarr' => isset($prices) ? $prices : null,
		      'orderby' => isset($orderby) ? $orderby : null,
            );

            $productCount = $this->_productInstance->searchProductCount($filter);
            $pageCount = 0;
            $eachPage = 0;
            if($pageRows == 'all' || $pageRows <= 0){
                $pageCount = 1;
                $eachPage = $productCount;
                $page = 1;
            }
            else{
                $eachPage = $pageRows;
                $pageCount = ceil($productCount/$pageRows);
            }
            $productList = $this->_productInstance->searchProductList($filter, $page, $eachPage);
            callFunction('getProductList', $this, $productList, $termInfo);

            $this->view->render('ajax/ajaxproductlist.phtml', array(
		      'productList' => isset($productList) ? $productList : null,
		  	  'page' => $page,
		  	  'pageRows' => $pageRows,
		      'orderby' => $orderby,
			  'filter'=>$filter,
		  	  'pageCount' => $pageCount,
		  	  'productCount' => $productCount,
		      'pagination' => callFunction('common_pagination', $urlPage, $pageCount, $page, $firstPath, true), 
            ));

        }
    }

    public function reviewAction($url = '' , $page = 1, $clear = null)
    {
        if ($url == '') {
            gotoUrl('review/all.html');
        }
        if (!preg_match ("/.html$/i", $url)) {
            goto404(t('no page'));
        }
        $taxonomyInstance = Taxonomy_Model::getInstance();
        $url = basename($url, '.html');
        list($directory_path_alias, $brand_path_alias, $tid_path_alias, $type, $fields, $prices, $page) = $urlArray = array_pad(explode('+', $url), 7, '');
        $urlPageFirst = $urlPage = $directory_path_alias . '+' . $brand_path_alias . '+' . $tid_path_alias . '+' . $type . '+' . $fields . '+' . $prices;
        $pvtitle = '';
        $relateName = array();
        if (isset($directory_path_alias) && $directory_path_alias && $directory_path_alias != 'all' && $directory_path_alias != 'allgoods') {
            $termInfo = $taxonomyInstance->getTermInfoByPathAlias($directory_path_alias);
            if (isset($termInfo) && $termInfo) {
                $directory_tid = isset($termInfo->tid) ? $termInfo->tid : null;

                $parent = $taxonomyInstance->getTermParents($termInfo->tid);
                $termInfo->parent = $parent;
                $termGrade = 1;
                if (isset($parent[0]) && $parent[0]) {
                    $termGrade = 2;
                }
                if (isset($parent[1]) && $parent[1]) {
                    $termGrade = 3;
                }
                $this->view->assign('termGrade', $termGrade);

                $pvid = $termInfo->pvid;
                $pvtitle .= str_replace('-', ' ', $directory_path_alias);
                $relateName[] =  $termInfo->name;
            } else {
                goto404(t('Can not found this Page'));
            }
        }

        if (isset($brand_path_alias) && $brand_path_alias) {
            $termInfo = $taxonomyInstance->getTermInfoByPathAlias($brand_path_alias);
            if (isset($termInfo) && $termInfo) {
                $brand_tid = isset($termInfo->tid) ? $termInfo->tid : null;
                $pvid = $termInfo->pvid;
                $pvtitle .= str_replace('-', ' ', $brand_path_alias);
                $relateName[] =  $termInfo->name;
            } else {
                goto404(t('Can not found this Page'));
            }
        }

        $tids = array();
        if (isset($tid_path_alias) && $tid_path_alias) {
            $tids_path_alias = explode('_', $tid_path_alias);
            foreach ($tids_path_alias as $path_alias) {
                $termInfo = $taxonomyInstance->getTermInfoByPathAlias($path_alias);
                if (isset($termInfo) && $termInfo) {
                    $tids[] = $termInfo->tid;
                    $pvid = $termInfo->pvid;
                    $pvtitle .= str_replace('-', ' ', $path_alias);
                    $relateName[] =  $termInfo->name;
                } else {
                    goto404(t('Can not found this Page'));
                }
            }
        }

        $pageInstance = PageVariable_Model::getInstance();
        if(isset($pvid)){
            $pageInfo = $pageInstance->selectPageVariables($pvid, 'term', $termInfo);
        }else{
            $pageInfo = $pageInstance->getPageVariableByKey('allgoods');
        }
        if (isset($pageInfo) && $pageInfo) {
            $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
        }

        //get most commented products.
        $termPathAlias = isset($termInfo->path_alias) && $termInfo->path_alias !== '' ? $termInfo->path_alias : 'product';
        $commentInstance = Comment_Model::getInstance();
        $configure = Bl_Config::get('widgetFiveStartsStatus');
        $mostCommentedProducts = $this->_productInstance->getPopularCommentedProducts($termInfo);
        foreach($mostCommentedProducts as $k=>$v){
            $v->url = $termPathAlias . '/' . ($v->path_alias !== '' ? $v->path_alias : $v->pid) . '.html';
            if($configure === '1'){
                $commentsByGrade = widgetCallFunction('fivestars', 'getcommentsbygrade', $v->pid, 5, 1);
                if($commentsByGrade && count($commentsByGrade) > 0){
                    $v->popularComment = $commentsByGrade[0];
                }
            }else{
                //get the comment that have the longest length.
                $commentList = $commentInstance->getCommentsListByProductId($v->pid, $filter = array('status' => 1, 'orderby'=> 'LENGTH(comment) DESC '));
                if($commentList && count($commentList > 0)){
                    $v->popularComment = $commentList[0];
                }
            }
        }

        $breadcrumb_tid = (isset($directory_tid) && $directory_tid) ? $directory_tid :
        ((isset($brand_tid) && $brand_tid) ? $brand_tid :
        ((isset($tids[0]) && $tids[0]) ? $tids[0] : 0));

        $breadcrumb = array();
        $breadcrumb[] = array(
      'title' => key_exists('productListHomeName',$this->_displayInfo) ? $this->_displayInfo['productListHomeName'] : 'Home',
      'path' => '',
        );

        $breadcrumb[] = array(
      'title' => 'Reviews',
        );

        if ($termInfo = $taxonomyInstance->getTermInfo($breadcrumb_tid)) {
            if (!$termInfo->ptid1) {
                $termParents['directory_tid1'] = $termInfo->tid;
            } else if (!$termInfo->ptid2) {
                $termParents['directory_tid1'] = $termInfo->ptid1;
                $termParents['directory_tid2'] = $termInfo->tid;
            } else if (!$termInfo->ptid3) {
                $termParents['directory_tid1'] = $termInfo->ptid1;
                $termParents['directory_tid2'] = $termInfo->ptid2;
                $termParents['directory_tid3'] = $termInfo->tid;
            } else {
                $termParents['directory_tid1'] = $termInfo->ptid1;
                $termParents['directory_tid2'] = $termInfo->ptid2;
                $termParents['directory_tid3'] = $termInfo->ptid3;
                $termParents['directory_tid4'] = $termInfo->tid;
            }
            if ($termParents) {
                foreach ($termParents as $tid) {
                    $term = $taxonomyInstance->getTermInfo($tid);
                    $product->directory[] = $term;
                    if($term->vid == 4){
                        $breadpath = 'review/++';
                    }elseif($term->vid == 3){
                        $breadpath = 'review/';
                    }elseif($term->vid == 2){
                        $breadpath = 'review/+';
                    }
                    $breadcrumb[] = array(
	            		'title' => t($term->name),
	            		'path' => isset($breadpath) ? ($breadpath. $term->path_alias . '.html') : categoryURL($term->path_alias),
                    );
                }
            }
        }
        setBreadcrumb($breadcrumb);

        $templateFile = (isset($termInfo->template) && $termInfo->template) ? $termInfo->template : 'reviewlist.phtml';

        $this->view->assign('pvtitle', isset($pvtitle) ? $pvtitle : null);
        $this->view->render($templateFile, array(
      'termInfo' => isset($termInfo) ? $termInfo : null,
      'mostCommentedProducts' => $mostCommentedProducts,
      'showSeeAllLink' => false,
        ));
    }
    
    
    
    public function custommeasurementsAction($pid){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
            goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
        }
        
        $backurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';    

        $customdata = isset($_SESSION['custom'])?  $_SESSION['custom'] : null;
    	
    	$this->view->render('custommeasurements.phtml', array(
    		'product' => $product,
    		'backurl' => $backurl,
    		'customdata' => $customdata,
    	));
    }
    
    public function viewsizechartAction($pid){
    	
    	$backurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    	
		$content = '';
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		$content = 'no found';
    	}
    	
    	$sizechartinstance = SizeChart_Model::getInstance();
    	$sizechart = $sizechartinstance->getSizeChartByBrand($product->brand_tid);
		if (isset($sizechart)) {
			$content = $sizechart->content;
		}
    	$this->view->render('product_sizechart.phtml', array(
    		'backurl' => $backurl,
    		'type' => $product->type,
    		'content' => $content,
    	));
    }
    
    
    public function viewcolorchartAction($pid){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		goto404();
    	}
    	 
    	$backurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    	 
    	$this->view->render('product_colorchart.phtml', array(
    			'backurl' => $backurl,
    	));
    }
    
    
   /*
    * 所有商品选择属性的action
    * @pid 商品pid
    * @type 属性名称：【颜色、尺寸、腰带颜色等……
    */
    public function selectpropertyAction($pid,$fieldtype){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
    	}
    	
    	$type = $product->type;
    	if ($this->_productInstance->checkTypeExist($type)) {
            $product->type = $this->_productInstance->getTypeInfo($type);
//             $product->fields = $this->_productInstance->getTypeFieldsList($type);
//             if (isset($product->type))
//             {
//                 $product->sizeChart = SizeChart_Model::getInstance()->getSizeChartByType($type);
//                 $product->measurement = Measurement_Mod	el::getInstance()->getMeasurementByType($type);
//             }
        } else {
            $product->type = null;
//             $product->fields = array();
//             $product->sizeChart = null;
        }
        
    	$fieldcontentlist = $this->_productInstance->getProductFieldContentList($product, $fieldtype);
    	
    	$this->view->render('select_property.phtml',array(
    			'product' => $product,
    			'type' => $fieldtype,
    			'fieldcontentlist' => $fieldcontentlist,
    			'currentvalue' => isset($_SESSION[$fieldtype]) ? $_SESSION[$fieldtype] : null,
    	));
    }
    
    public function topmenuAction(){
    	$termchilds = Taxonomy_Model::getInstance()->getTermsList(3);
    	
    	$this->view->setTitle('Adoringdress Categories');
    	
    	$this->view->render("catalogue.phtml", array(
    			'lists' => $termchilds,
    	));
    }
    
    
    public function viewdescriptionAction($pid){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
    	}
    	
    	$this->view->render("product_description.phtml",array(
    		'product' =>$product,
    	));
    }
    
    public function viewspecificationAction($pid){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
    	}
    	
    	$type = $product->type;
    	if ($this->_productInstance->checkTypeExist($type)) {
//     		$product->type = $this->_productInstance->getTypeInfo($type);
    		$product->fields = $this->_productInstance->getTypeFieldsList($type);
    		// 根据product的type获取SizeChart, Measurement信息。
    	} else {
//     		$product->type = null;
    		$product->fields = array();
//     		$product->sizeChart = null;
    	}

    	$this->view->render("product_specification.phtml",array(
    			'product' =>$product,
    	));
    }
    
    // 这里是每页评论都要翻页，  ajax的翻页代码在  comment_controller里面
    public function viewreviewsAction($pid, $page = 1){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
    	}
    	
    	$commentInstance = Comment_Model::getInstance();
    	$comments = $commentInstance->getCommentsListByProductId($product->pid,array(), $page, 3);
    	$commentcount = $commentInstance->getCommentsCountByProductId($product->pid);
    	
    	global $user;
    	$canComment = User_Model::getInstance()->validateUserPurchaseProduct($user->uid, $product->pid);
    	
    	$grades = widgetCallFunction('fivestars', 'getStars', $pid);
    	$gradesTotal = 0;
    	$sumTotal = 0;
    	$fullcount = 0;
    	$showstar = false;
    	if(isset($grades)){
    		foreach($grades as $k=> $v){
    			$gradesTotal += $k * $v;
    			$sumTotal += $v;
    		}
    		if($sumTotal > 0){
    			$avggrades = round($gradesTotal/$sumTotal, 2);
    			$fullcount = intval($avggrades);
    			if(strpos($avggrades, '.')){
    				$halftemp = explode(".", $avggrades);
    				if($halftemp[1] >= 0.5){
    					$fullcount += 0.5;
    				}
    			}
    			$showstar = true;
    		}
    	}
    	
    	$this->view->render("product_reviews.phtml", array(
    		'product' => $product,
    		'comments' => $comments,
    		'pagination' => callFunction('combo_pagination', url('product/viewreviews/').$product->pid."/%d/", ceil($commentcount/5), $page),
    		'canComment' => $canComment,
    		'showstar' => $showstar,
    		'star' => $fullcount,
    		'count' => $sumTotal,
    	));
    }
    
    public function writereviewAction($pid){
    	if (!$product = $this->_productInstance->getProductInfo($pid)) {
    		goto404(t('Product ID'). '<em>' . $pid . '</em>' . t('not found.'));
    	}

    	$userInstance = User_Model::getInstance();
    	if (!$userInstance->logged()) {
//     		$_SESSION['redirect_url'] = 'product/writereview/'.$pid;
    		gotoUrl('user/login');
    	}
    	global $user;
    	$canComment = User_Model::getInstance()->validateUserPurchaseProduct($user->uid, $pid);
    	
    	if($canComment){
    		$_SESSION['comment_token'] = $productCommentToken = randomString(16);
    		 
    		$sourceurl = $_SERVER['HTTP_REFERER'];
    		 
    		$this->view->render("product_writereview.phtml", array(
    				'product' => $product,
    				'comment_token' => $productCommentToken,
    				'backurl' => isset($sourceurl) ? $sourceurl : url($product->url),
    		));
    	}
    	else{
    		gotoUrl($product->url);
    	}
    }
    
    public function viewpictureAction($pid){
    	$product = $this->_productInstance->getProductInfo($pid);
    	$product->files = array_values($this->_productInstance->getProductFilesList($pid));
    	$this->view->render("product_viewpicture.phtml",array(
    		'product' => $product,
    	));
    }
    
    public function ajaxsetpropertyAction(){
    	if($this->isPost()){
    		$backjson = array();
    		try {
    			$post = $_POST;
    			$propertytype = $post['type'];
    			if($propertytype == "size"){
    				unset($_SESSION['custom']);
    			}
    			$propertyvalue = $post['value'];
    			$_SESSION[$propertytype] = $propertyvalue;
    			$backjson['msg'] = 'success';
    		} catch (Exception $e) {
    			$backjson['msg'] = 'error';
    		}
    		$backstr = json_encode($backjson);
    		echo $backstr;
    	}
    }
    
    public function ajaxsshowmoreAction($url){
    	$post = $_POST;
    	$offset = $post['offset'];
    	$page = 1;
		$filter = $this->parseBrowseUrlParams($url, $page);
    	
    	$productList = $this->_productInstance->searchProductList($filter, $offset, 12);
    	
    	$this->view->render('ajax/ajaxshowmoreproducts.phtml', array(
    		'productList' => $productList,
    	));

    }
    
    public function ajaxshowmorereviewsAction(){
    	$post = $_POST;
    	$pid = $post['pid'];
    	$offset = $post['offset'];

    	$commentInstance = Comment_Model::getInstance();
    	$comments = $commentInstance->getCommentsListByProductId($pid,array(), $offset, 3);
    	
    	$this->view->render('ajax/ajaxshowmorereviews.phtml',array(
    		'comments' => $comments,
    	));
    	
    	
    	//  以下是如果采用js添加dom标签的方式的后台代码
//     	$post = $_POST;
//     	$pid = $post['pid'];
//     	$offset = $post['offset'];
    	
//     	$commentInstance = Comment_Model::getInstance();
//     	$comments = $commentInstance->getCommentsListByProductId($pid,array(), $offset, 3);
//     	$commentcount = $commentInstance->getCommentsCountByProductId($pid);
    	
//     	$reviewslist = array();
    	 
//     	foreach ($comments as $v) {
//     		$comment = array();
//     		$comment['nickname'] = $v->nickname;
    	
//     		$info = widgetCallFunction('fivestars', 'getcommentstarsGrade', $v->cid);
//     		$comment['star'] = $info->grade;
    	
//     		$comment['title'] = $v->subject;
//     		$comment['content'] = $v->comment;
    	
//     		if(empty($v->photo_paths)){
//     			$comment['has_pic'] = false;
//     		}else{
//     			$comment['has_pic'] = true;
//     			$comment['pics'] = array();
//     			foreach($v->photo_paths as $pic){
//     				$temp = array();
//     				$temp['largeurl'] = urlimg('water_mark', $pic);
//     				$temp['smallurl'] = urlimg("85x85", $pic);
//     				$comment['pics'][] = $temp;
//     			}
//     		}
//     		$reviewslist[] = $comment;
//     	}
    	
//     	$jsondata = array(
//     		'success' => true,
//     		'offset' => 5,
//     		'each_page' => 3,
//     		'reviewslist' => $reviewslist,
//     	);
//     	echo json_encode($jsondata);
    }
    
    public function addtowishAction(){
    	if ($this->isPost()) {
		    global $user;
		  	$userInstance = User_Model::getInstance();
		  	if (!$userInstance->logged()) {
		  		gotoUrl('user/login');
		  	}
		  	
    		$post = $_POST;
    		if(isset($post['cart'])){
    			$data = isset($post['data']) ? $post['data'] : null;
    			$productInstance = Product_Model::getInstance();
    			$product = $productInstance->getProductInfo($post['pid']);
    			$type = $product->type;
    			if ($productInstance->checkTypeExist($type)) {
    				$product->fields = $productInstance->getTypeFieldsList($type);
    			} else {
    				$product->fields = array();
    			}
    			
    			foreach ($product->fields as $fieldName => $field) {
    				if(!empty($product->{'field_' . $fieldName}) && !$field->is_spec && $field->required && empty($data[$fieldName]) && empty($data[ucfirst($fieldName)])) {
    					$reffer_url = $_SERVER["HTTP_REFERER"];
    					echo "<script>alert('Please select your ". $fieldName . "');window.location.replace('" . $reffer_url . "');</script>";
    					exit;
    				}
    			}
    			$wishlistInstance = WishList_Model::getInstance();
    			if($wishlistInstance->addToWishList($post['pid'], $post['qty'], $data, false)){
     				gotoUrl("user/wishlist");
    			}else{
    				
    			}
    		}else{
    			goto404(t('Access Denied'));
    		}
    	}else{
    		goto404(t('Access Denied'));
    	}
    }
    
    
}
