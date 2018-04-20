<?php
class Promotion_Controller extends Bl_Controller
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
        'action' => 'viewpromotion',
        'arguments' => $paths,
      );
    }
  }


  public function init()
  {
    $this->_productInstance = Product_Model::getInstance();
  }


  /**
   * 促销活动查看
   * 2010-10-18 Added By 55Feng
   *
   * @param $pmid
   */
  public function viewpromotionAction($path)
  {
    $pmid = $this->_productInstance->getPromotionIDByAlias($path);
    if (!$pmid) {
      goto404(t('Promotion') . '<em>' . $path . '</em>' . t('not found.'));
    }

    $promotionInfo = $this->_productInstance->getPromotionInfo($pmid);


    //查询活的商品列表
    $pidList = $this->_productInstance->getPromotionPidList($pmid);
    $productList = array();
    foreach ($pidList as $key => $pid) {
      $productList[$pid] = $this->_productInstance->getProductInfo($pid);
    }

    $breadcrumb = array();
    $displayInfo = Bl_Config::get('display', array());
    $breadcrumb[] = array(
      'title' => isset($displayInfo['productListHomeName']) ? $displayInfo['productListHomeName'] : 'Home',
      'path' => '',
    );

    $breadcrumb[] = array(
      'title' => $promotionInfo->name,
      'path' => 'promotion/'.$path.'.html',
    );

    $breadcrumb[] = array(
      'title' => 'Promotion',
    );
    setBreadcrumb($breadcrumb);

    $this->view->render('promotion.phtml', array(
      'promotion' => $promotionInfo,
      'productList' => $productList,
    ));
  }


}

?>