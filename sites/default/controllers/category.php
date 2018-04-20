<?php
class Category_Controller extends Bl_Controller
{
  private $_taxonomyInstance;

  public static function __router($paths)
  {
    if (!isset($paths[0])) {
      goto404(t('Argument 0 is invalid'));
    }
    if (preg_match('/^([\w-]+)\.html$/i', $paths[0], $matches)) {
      $pathstr = $matches[1];
      $patharr = explode('_', $pathstr);
      $paths = $patharr;
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
    $this->_taxonomyInstance = Taxonomy_Model::getInstance();
    $this->_displayInfo = Bl_Config::get('display', array());
  }

  public function indexAction()
  {
  	gotoUrl('category/list');
  }

  public function viewAction($pid, $page = 1)
  {
    if (!($termInfo = $this->_taxonomyInstance->getTermInfo($pid)) || !$termInfo->visible) {
      goto404(t('Page no found'));
    }
    $this->_view($termInfo, $page);
  }

  public function pathaliasAction($pathAlias, $page = 1)
  {
    if (!($termInfo = $this->_taxonomyInstance->getTermInfoByPathAlias($pathAlias)) || !$termInfo->visible ) {
      goto404(t('Page no found'));
    }
    $this->_view($termInfo, $page);
  }

  public function _view($termInfo, $page = 1)
  {
    $page = isset($page) && $page ? $page : 1;

    $pageRows = isset($_SESSION['browseListConfig']['pageRows']) ? $_SESSION['browseListConfig']['pageRows'] : ($this->_displayInfo['goodsListNum'] ? $this->_displayInfo['goodsListNum'] : 20);
    $listMode = isset($_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo';
    $orderby = isset($_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null;

    $breadcrumb = array();
    $breadcrumb[] = array(
      'title' => isset($this->_displayInfo['productListHomeName']) ? $this->_displayInfo['productListHomeName'] : 'Home',
      'path' => '',
    );
    $termParents = $this->_taxonomyInstance->getTermParents($termInfo->tid);
    $termParents = $termParents ? array_reverse($termParents) : array();

    foreach ($termParents as $tid) {
    	$termInfoparent = $this->_taxonomyInstance->getTermInfo($tid);
    	$breadcrumb[] = array(
        'title' => $termInfoparent->name,
      	'path' => 'category/' . $termInfoparent->path_alias . '.html',
      );
    }
    $breadcrumb[] = array(
      'title' => $termInfo->name,
    );

    setBreadcrumb($breadcrumb);

    $productInstance = Product_Model::getInstance();

    $cacheId = 'frontTermInfo-' . $termInfo->tid . '-' . $page;
    if($cache = cache::get($cacheId)) {
      list($pageInfo, $productList, $productCount) = $cache->data;
    } else {
      $pageInstance = PageVariable_Model::getInstance();
      $pageInfo = $pageInstance->selectPageVariables($termInfo->pvid, 'term', $termInfo);
      if ($termInfo->vid == Taxonomy_Model::TYPE_DIRECTORY) {
        $productList = $productInstance->getProductsList(array('tids' => $termInfo->tid, 'orderby' => $orderby), $page, $pageRows);
        $productCount = $productInstance->getProductsCount(array('tids' => $termInfo->tid));
      } else if ($termInfo->vid == Taxonomy_Model::TYPE_BRAND) {
        $productList = $productInstance->getProductsList(array('brand_tid' => $termInfo->tid, 'orderby' => $orderby), $page, $pageRows);
        $productCount = $productInstance->getProductsCount(array('brand_tid' => $termInfo->tid));
      } else {
        $productList = $productInstance->getProductsListBySpecial(array('special_tid' => $termInfo->tid, 'orderby' => $orderby), $page, $pageRows);
        $productCount = $productInstance->getProductsCountBySpecial(array('special_tid' => $termInfo->tid));
      }
      cache::save($cacheId, array($pageInfo, $productList, $productCount));
    }

    if (isset($pageInfo) && $pageInfo) {
      $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
    }

    $templateFile = isset($termInfo->template) && $termInfo->template != '' ? $termInfo->template : 'termsinfo.phtml';

    $reffer_url = ltrim($_SERVER["REQUEST_URI"], "/");
    $pathinfo = pathinfo($reffer_url);
    $path_alias_arr = explode('_', $pathinfo['filename']);
    $path_alias = $path_alias_arr[0];
    $pageurl = $pathinfo['dirname'] . '/' . $path_alias .'_%d.html';
    $pageCount = ceil($productCount/$pageRows);

    if ($page == 1) {
      $_SESSION['FirstPath']['categoryList'] = trim($_SERVER['REQUEST_URI'], '/');
    }
    $firstPath = isset($_SESSION['FirstPath']['categoryList']) ? $_SESSION['FirstPath']['categoryList'] : null;

    $this->view->render($templateFile, array(
      'termInfo' => isset($termInfo) ? $termInfo : null,
      'page' => $page,
      'pageRows' => $pageRows,
      'termParents' => isset($termParents) ? $termParents : array(),
      'listMode' => $listMode,
      'orderby' => isset($orderby) ? $orderby : null,
      'pageCount' => $pageCount,
      'productCount' => $productCount,
      'productList' => isset($productList) ? $productList : null,
      'pagination' => $productCount ? callFunction('pagination', $pageurl, $productCount, $pageRows, $page, $firstPath) : null,
    ));
  }

  public function listAction()
  {
  	$termsList = $this->_taxonomyInstance->getTermsList(Taxonomy_Model::TYPE_DIRECTORY, false);
    $this->view->render('termslist.phtml', array(
      'productList' => isset($termsList) ? $termsList : null,
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
        $url = $pathinfo['dirname'] . '/' . $path_alias . '.html';
      } else {
        $_SESSION['browseListConfig']['pageRows'] = (isset($post['pageRows']) && $post['pageRows']) ? $post['pageRows'] : (
          ((isset($_SESSION['browseListConfig']['pageRows']) && $_SESSION['browseListConfig']['pageRows']) ? $_SESSION['browseListConfig']['pageRows'] : ($this->_displayInfo['goodsListNum'] ? $this->_displayInfo['goodsListNum'] : 20))
          );
        $_SESSION['browseListConfig']['listMode'] = (isset($post['listMode']) && $post['listMode']) ? $post['listMode'] : (
          ((isset($_SESSION['browseListConfig']['listMode']) && $_SESSION['browseListConfig']['listMode']) ? $_SESSION['browseListConfig']['listMode'] : 'photo')
          );
        $_SESSION['browseListConfig']['orderby'] = (isset($post['orderby']) && $post['orderby']) ? $post['orderby'] : (
          ((isset($_SESSION['browseListConfig']['orderby']) && $_SESSION['browseListConfig']['orderby']) ? $_SESSION['browseListConfig']['orderby'] : null)
          );
        $page = isset($post['page']) && $post['page'] ? $post['page'] : 1;
        $url = $pathinfo['dirname'] . '/' . $path_alias . '_' . $page . '_' . $listMode  . '_' . $orderby . '_' . $pageRows . '.html';
      }
      header('Location:' . $url);exit;
    } else {
      $url = $pathinfo['dirname'] . '/' . $path_alias . '.html';
      header('Location : ' . $url);
    }
  }
}