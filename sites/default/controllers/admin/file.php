<?php
class Admin_File_Controller extends Bl_Controller
{
  private $_fileModel;
  
  public function init()
  {
    $this->_fileModel = File_Model::getInstance();
  }
  
  public function indexAction()
  {
    gotoUrl('admin/file/getList');
  }
  
  public function getListAction($pid = 0)
  {
    global $user;
    $productModel = Product_Model::getInstance();
    $filesList = $productModel->getProductFilesList($pid);
    $this->view->addJs(url('scripts/swfupload-jquery/vendor/swfupload/swfupload.js'));
    $this->view->addJs(url('scripts/swfupload-jquery/src/jquery.swfupload.js'));
    $this->view->addCss(url('scripts/swfupload-jquery/css/default.css'));
    $this->view->render('admin/product/fileslist.phtml', array(
      'filesList' => $filesList,
    ));
  }

  public function savefileAction($type = 'product')
  {
    if (isset($_POST['PHPSESSID']) && $_POST['PHPSESSID']) {
      session::read($_POST['PHPSESSID']);
    }
    global $user;
    if (isset($_FILES['filedata'])) {
    	$post = array('type' => $type);
      $file = $this->_fileModel->insertFile('filedata', $post);
      if ($file) {
        $filepath = url($file->filepath, false);
        $fileArray = array(
          'fid' => $file->fid,
          'filename' => $file->filename,
          'filepath' => $filepath,
        );
        echo json_encode($fileArray);
      } else {
        echo 0;
      }
    } else {
      echo -1;
    }
  }
  
  public function editoruploadAction($type)
  {
    global $user;
    $result = array(
      'err' => 'Upload error.',
      'msg' => '',
    );
    if (isset($_FILES['filedata'])) {
      $post = array('type' => $type);
      $file = $this->_fileModel->insertFile('filedata', $post);
      if ($file) {
        $result['err'] = '';
        $result['msg'] = array(
          'id' => $file->fid,
          'url' => url('images/' . $file->filepath, false),
          'localfile' => $file->filename,
        );
      }
    }
    echo json_encode($result);
  }
}