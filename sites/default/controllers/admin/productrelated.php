<?php
class Admin_Productrelated_Controller extends Bl_Controller
{
  private $_productInstance;

  public static function __permissions()
  {
    return array(
      'list productrelated',
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    if (!access('list productrelated')) {
      goto403('Access Denied.');
    }
    $this->_productInstance = Product_Model::getInstance();
  }

  public function firstListAction($key)
  {
    if ($key == 'all') {
      foreach ($_SESSION['listproductrelated'] as $key1 => $dl) {
        unset($_SESSION['listproductrelated'][$key1]);
      }
    } else {
      unset($_SESSION['listproductrelated'][$key]);
    }
    gotourl('admin/productrelated/addlist');
  }

  public function addlistAction($page = 1) {
    $filter = array();
    if ($this->isPost()) {
    	$post = $_POST;
      isset($post['status']) && $post['status'] == 'on' ? $post['status'] = 1 : $post['status'] = 0;
      if ($post['directory_tid4']) {
        $post['tids'] = $post['directory_tid4'];
      } else if ($post['directory_tid3']) {
        $post['tids'] = $post['directory_tid3'];
      } else if ($post['directory_tid2']) {
        $post['tids'] = $post['directory_tid2'];
      } else if ($post['directory_tid1']) {
        $post['tids'] = $post['directory_tid1'];
      } else {
        $post['tids'] = 0;
      }
      unset($post['directory_tid1']);
      unset($post['directory_tid2']);
      unset($post['directory_tid3']);
      unset($post['directory_tid4']);
      $filter = $post;
      foreach ($post as $key=>$dl) {
        if ($dl) {
         $_SESSION['listproductrelated'][$key] = $dl;
        }
      }
    } else {
      if(isset($_SESSIN['listproductrelated'])){
        $filter = $_SESSION['listproductrelated'];
      }
    }
    $productsList = $this->_productInstance->getProductsList($filter, $page, 12);
    $productsCount = $this->_productInstance->getProductsCount($filter);
    $typeList = $this->_productInstance->getTypeList();
    $taxonomyInstance = new Taxonomy_Model();
    $directoryTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY, false);
    $selectHtml = $this->_productInstance->getSelectHtml($filter, $typeList, $directoryTermsList, 'admin/productrelated/firstList/');
    $this->view->render('admin/product/productrelatedlist.phtml', array(
      'productsList' => $productsList,
      'selectHtml' => $selectHtml,
      'pagination' => pagination('admin/productrelated/addlist/%d' . (isset($status) ? ('/' . $status) : ''), $productsCount, 12, $page),
    ));
  }

  public function listAction($pid, $page = 1) {
    $productsRelatedCount = $this->_productInstance->countProductRelated($pid);
    $productsRelatedList = $this->_productInstance->listProductRelated($pid);
    $this->view->render('admin/product/productrelatedlist.phtml', array(
      'productsRelatedCount' => $productsRelatedCount,
      'paginationRelated' => pagination('admin/productrelated/list/%d', $productsRelatedCount, 10, $page),
    ));
  }

  public function selectAction($page = 1) {
      $taxonomyInstance = Taxonomy_Model::getInstance();
      $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
      $directoryTreeList = $taxonomyInstance->getTermsListForHtmlTree($directoryList);
      $typeList = $this->_productInstance->getTypeList();
      $productsList = $this->_productInstance->getProductsList(null, 1, 20);
      $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
      $this->view->render('admin/product/productrelatedselect.phtml', array(
        'productsList' => $productsList,
        'directoryList' => $directoryList,
        'typeList' => $typeList,
      ));
    }
}