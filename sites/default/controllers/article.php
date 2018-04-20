<?php
class Article_Controller extends Bl_Controller
{
  private $_articleInstance;

  public static function __router($paths)
  {
    if (!isset($paths[0])) {
      return;
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

  public function indexAction(){
    $this->listAction();
  }

  public function init()
  {
    $this->_articleInstance = Content_Model::getInstance();
    $this->_displayInfo = Bl_Config::get('display', array());
  }

  public function viewAction($aid)
  {
    if (!($articleInfo = $this->_articleInstance->getArticleInfo($aid)) || !$articleInfo->status) {
      goto404(t('Article no found'));
    }
    $this->_show($articleInfo);
  }

  public function pathaliasAction($pathAlias)
  {
    if (!($articleInfo = $this->_articleInstance->getArticleInfoBypathAlias($pathAlias)) || !$articleInfo->status ) {
      goto404(t('Article no found'));
    }
    $this->_show($articleInfo);
  }

  public function _show($articleInfo)
  {
  	//文章分类信息
  	callFunction('article', 'before', $articleInfo);
    $articleInfo->firstType = $this->_articleInstance->getArticleTypeInfo($articleInfo->atid);
    if ($articleInfo->firstType && $articleInfo->secondType = $this->_articleInstance->getArticleTypeInfo($articleInfo->firstType->parent)) {
    	list($articleInfo->firstType, $articleInfo->secondType) = array($articleInfo->secondType, $articleInfo->firstType);
    }
    
    $this->_articleInstance->updateVisits($articleInfo->aid);
    
    $breadcrumb = array();
    $breadcrumb[] = array(
      'title' => isset($this->_displayInfo['productListHomeName']) && $this->_displayInfo['productListHomeName'] ? $this->_displayInfo['productListHomeName'] : 'Home',
      'path' => '',
    );

    if (isset($articleInfo->firstType) && $articleInfo->firstType) {
    	$breadcrumb[] = array(
	      'title' => $articleInfo->firstType->name,
	      'path' => $articleInfo->firstType->url,
      );
    }
    if (isset($articleInfo->secondType) && $articleInfo->secondType) {
      $breadcrumb[] = array(
        'title' => $articleInfo->secondType->name,
        'path' => $articleInfo->secondType->url,
      );
    }
    $breadcrumb[] = array(
      'title' => $articleInfo->title,
    );
    setBreadcrumb($breadcrumb);
    if (isset($articleInfo->pvid)) {
      $pageInstance = PageVariable_Model::getInstance();
      $pageInfo = $pageInstance->selectPageVariables($articleInfo->pvid, 'article', $articleInfo);
      if (isset($pageInfo) && $pageInfo) {
        $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description , $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
      }
    }
    $atid = isset($articleInfo->secondType->atid) ? $articleInfo->secondType->atid : (isset($articleInfo->firstType->atid) ? $articleInfo->firstType->atid : null);
   if($atid)
   {
      $this->_articleInstance->getClosesArticle($articleInfo, $articleInfo->aid, $atid);
    }
    
    $this->view->setTitle(ucfirst($articleInfo->title) . ' | Adoringdress');
    
    $this->view->render('article.phtml', array(
      'articleInfo' => isset($articleInfo) ? $articleInfo : null,
    ));
  }

  public function listAction($path)
  {
  	$path_alias = substr($path, 0, strpos($path, '.html'));
  	$name = ucwords(str_replace('-', ' ', $path_alias));
  	$this->view->render('articlelist.phtml', array(
  		'title' => $name,
  		'template' => $path_alias,
  	));
//   	$filter = array();
//   	$matches = array();
//   	if (preg_match('/^([\w-]+)\.html$/i', $path, $matches)) {
//   	  list($path_alias, $page, $pageRows) = array_pad(explode('_', $matches[1]), 3, 0);
//   	  $articleInfo = $this->_articleInstance->getArticleTypeInfoByPath($path_alias);
//   	  $atid = isset($articleInfo->atid) ? $articleInfo->atid : null;
//   	} else {
//   	  goto404();
//   	}
//   	if (!$page) {
//   	  $page = 1;
//   	}
//     $filter['atid'] = $atid == 'all' ? 0 : $atid;
//   	$articleInfo->firstType = $this->_articleInstance->getArticleTypeInfo($filter['atid']);
//     if ($articleInfo->firstType && $articleInfo->secondType = $this->_articleInstance->getArticleTypeInfo($articleInfo->firstType->parent)) {
//       list($articleInfo->firstType, $articleInfo->secondType) = array($articleInfo->secondType, $articleInfo->firstType);
//     }
    
//   	if (isset($articleInfo->pvid)) {
//       $pageInstance = PageVariable_Model::getInstance();
//       $pageInfo = $pageInstance->selectPageVariables($articleInfo->pvid, 'articletype', $articleInfo);
//       if (isset($pageInfo) && $pageInfo) {
//         $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description , $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
//       }
//     }
    
//     $breadcrumb = array();

//     $breadcrumb[] = array(
//       'title' => isset($this->_displayInfo['productListHomeName']) ? $this->_displayInfo['productListHomeName'] : 'Home',
//       'path' => '',
//     );
//     $breadcrumb[] = array(
//       'title' => 'article',
//       'path' => 'articletype/all.html',
//     );
//     if (isset($articleInfo->firstType) && $articleInfo->firstType) {
//     	$breadcrumb[] = array(
//         'title' => $articleInfo->firstType->name,
//         'path' => $articleInfo->firstType->url,
//       );
//     }
//     if (isset($articleInfo->secondType) && $articleInfo->secondType) {
//     	$breadcrumb[] = array(
//         'title' => $articleInfo->secondType->name,
//         'path' => $articleInfo->secondType->url,
//       );
//     }
//     setBreadcrumb($breadcrumb);
    
//     $articleListByType= array();
//     //special treatment for all page of articles.
// /*    if($path == 'all.html'){
//       $articleTypeList = $this->_articleInstance->getArticleTypeList();
//       foreach ($articleTypeList as $k => $v){
//         $articleList = $this->_articleInstance->getArticleList(array('atid'=>$v->atid), $page, $pageRows);
//         $articleListByType[$v->name] = $articleList;
//         $this->view->render('articlelist_all.phtml', array(
//                'articleInfo' => $articleInfo,
//                'articleListByType' => $articleListByType,
//               ));
//       }
//     }else{*/
//     $articleList = $this->_articleInstance->getArticleList($filter, $page);
//     $articlecount = $this->_articleInstance->getArticleCount($filter);

//     if ($page == 1) {
//       $_SESSION['FirstPath']['articleList'] = trim($_SERVER['REQUEST_URI'], '/');
//     }
//     $firstPath = isset($_SESSION['FirstPath']['articleList']) ? $_SESSION['FirstPath']['articleList'] : null;
    
//     $this->view->setTitle(ucfirst($articleInfo->name) . ' | Adoringdress');
    
//     $this->view->render('articlelist.phtml', array(
//      'articleInfo' => $articleInfo,
//      'articleList' => $articleList,
//     ));
  }
}