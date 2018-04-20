<?php
class Admin_Vocabulary_Controller extends Bl_Controller
{
  const TERM_TYPE_TAG = 1;
  const TERM_TYPE_BRAND = 2;
  const TERM_TYPE_RECOMMEND = 3;
  const TERM_TYPE_DIRECTORY = 4;
  
  private $_taxonomyModel;
  
  public static function __permissions()
  {
    return array(
      'list vocabulary',
      'edit vocabulary',
      'delete vocabulary',
    );
  }
  
  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_taxonomyModel = Taxonomy_Model::getInstance();
  }
  
  public function indexAction()
  {
    $this->getListAction();
  }
  
  public function getListAction()
  {
    if (!access('list vocabulary')) {
      goto403('Do not have access');
    }
    $vocabularyList = $this->_taxonomyModel->getVocabularyList();
    $this->view->render('admin/vocabulary/list.phtml', array(
      'vocabularyList' => $vocabularyList,
    ));
  }
  
  public function getInfoAction($vid)
  {
    if (!access('list vocabulary')) {
      goto403('Do not have access');
    }
    $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid($vid);
    if (!$vocabularyInfo->vid) {
      setMessage('This vocabulary can not found!', 'error');
      gotoUrl('admin/vocabulary/getList');
    }
    $this->view->render('admin/vocabulary/info.phtml',array(
      'vocabularyInfo' => $vocabularyInfo,
    ));
  }
  
  public function insertAction()
  {
    if (!access('edit vocabulary')) {
      goto403('Do not have access');
    }
    if ($this->isPost()) {
    	$_POST['name'] = trim($_POST['name']);
      if (!$_POST['name']) {
      	setMessage('Vocabulary Name can not null', 'error');
      	gotoUrl('admin/vocabulary/insert');
      }
    	$vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByName($_POST['name']);
      if (!$vocabularyInfo->vid) {
        $result = $this->_taxonomyModel->insertVocabulary($_POST);
        if ($result) {
          gotoUrl('admin/Vocabulary/getList');
        } else {
         setMessage('新增分类错误', 'error');
         gotoUrl('admin/vocabulary/insert');
        }
      } else {
        setMessage('该分类已经存在', 'error');
        gotoUrl('admin/vocabulary/insert');
      }
      $this->view->render('admin/vocabulary/add.phtml');
    } else {
      $this->view->render('admin/vocabulary/add.phtml');
    }
  }
  
  public function updateAction()
  {
    if ($this->isPost()) {
	    if (!access('edit vocabulary')) {
	      goto403('Do not have access');
	    }
      $_POST['name'] = trim($_POST['name']);
      if (!$_POST['name']) {
        setMessage('Vocabulary Name can not null', 'error');
        gotoUrl('admin/vocabulary/getInfo/'.$_POST['vid']);
      }
      $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByName($_POST['name']);
      if (empty($vocabularyInfo->vid) || $vocabularyInfo->vid == $_POST['vid']){
        if ($this->_taxonomyModel->updateVocabulary($_POST['vid'],$_POST)) {
          gotoUrl('admin/Vocabulary/getList');
        } else {
          setMessage('修改分类错误', 'error');
          gotoUrl('admin/vocabulary/getInfo/'.$_POST['vid']);
        }
      } else {
        setMessage('该分类已经存在，不能修改为该名称', 'error');
        gotoUrl('admin/vocabulary/getInfo/'.$_POST['vid']);
      }
      $this->view->render('admin/vocabulary/add.phtml');
    } else {
      $this->view->render('admin/vocabulary/info.phtml');
    }
  }
 
  public function deleteAction($vid)
  {
    if (!access('delete vocabulary')) {
      goto403('Do not have access');
    }
    $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid($vid);
    if (!$vocabularyInfo->vid) {
      setMessage('This vocabulary can not found!', 'error');
    } else {
      if ($vocabularyInfo->type == 0) {
        $result = $this->_taxonomyModel->deleteVocabulary($vid);
        if ($result) {
          gotoUrl('admin/vocabulary/getlist');
        } else {
          setMessage('删除分类失败', 'error');
        }
      } else {
        setMessage('默认分类不能删除', 'error');
      }
    }
    gotoUrl('admin/vocabulary/getlist');
  }
}
