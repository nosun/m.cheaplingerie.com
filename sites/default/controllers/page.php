<?php
class Page_Controller extends Bl_Controller
{
  private $_pageInstance;

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
    $this->_pageInstance = Content_Model::getInstance();
  }

  public function viewAction($pid)
  {
    if (!($pageInfo = $this->_pageInstance->getPageInfo($pid)) || !$pageInfo->visible) {
  		goto404(t('Page no found'));
  	}
  	$pageList = $this->_pageInstance->getPageList(null, null);
  	$this->_show($pageInfo, $pageList);
  }

  public function pathaliasAction($pathAlias)
  {
  	if (!($pageInfo = $this->_pageInstance->getPageInfoBypathAlias($pathAlias)) || !$pageInfo->visible ) {
      goto404(t('Page no found'));
    }
  	$pageList = $this->_pageInstance->getPageList(null, null);
  	$this->_show($pageInfo, $pageList);
  }

  public function _show($pagecontent, $pageList)
  {
    if (isset($pagecontent->pvid)) {
      $pageInstance = PageVariable_Model::getInstance();
      $pageInfo = $pageInstance->selectPageVariables($pagecontent->pvid, 'page', $pagecontent);
      if (isset($pageInfo) && $pageInfo) {
        $this->view->setTitle($pageInfo->title, $pageInfo->meta_keywords, $pageInfo->meta_description, $pageInfo->var1, $pageInfo->var2, $pageInfo->var3, $pageInfo->var4, $pageInfo->var5, $pageInfo->var6);
      }
    }
    $breadcrumb = array();
    $displayInfo = Bl_Config::get('display', array());
    $breadcrumb[] = array(
      'title' => isset($displayInfo['productListHomeName']) ? $displayInfo['productListHomeName'] : 'Home',
      'path' => '',
    );
    $breadcrumb[] = array(
      'title' => $pagecontent->title,
    );
    setBreadcrumb($breadcrumb);
  	$this->view->render('page.phtml', array(
      'pageInfo' => isset($pagecontent) ? $pagecontent : null,
      'pageList' => isset($pageList) ? $pageList:null,
    ));
  }
  
  public function ajaxsavechartpositionAction(){
  	if($this->isPost()){
  		$post = $_POST;
  		$left = $post['left'];
  		$top = $post['top'];
  		$_SESSION['chatleft'] = $left.'px';
  		$_SESSION['chattop'] = $top.'px';
  	}
  }
}