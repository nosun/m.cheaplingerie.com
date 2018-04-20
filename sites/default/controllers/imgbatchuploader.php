<?php
class Imgbatchuploader_Controller extends Bl_Controller
{
  public function uploadAction()
  {
  	$post = $_POST;
  	//recieve the files.
  	$pid = $post['pid'];
  	log::save('DEBUG', 'upload image information', 'in upload image thread');
  	//need to get file fid.
  	$files_info = array();
  	$productInstance = Product_Model::getInstance();
  	$fileInstance = File_Model::getInstance();
  	
  	$isFirstFile = true;
    foreach($_FILES as $fileName=>$fileArray){
  		//move_uploaded_file($fileArray["tmp_name"], $targetDir.$fileArray["name"]);
  		if (!$fileArray['error'] && $fileArray['size']) {
          $filepost = array('type' => 'product');
          $file = $fileInstance->insertFile($fileName, $filepost);
          //get the file fid from file
          //set the file as product file.
          if($isFirstFile){
          	$productInstance->updateProductFile($pid, $file->fid);
          	$isFirstFile = false;
          }
          $dl = new stdClass();
          $dl->alt = '';
          $dl->fid = $file->fid;
          $dl->weight = 0;
          $files_info[$file->fid] = $dl;
        }else{
        	echo $fileArray['error'];
        	log::save('DEBUG', 'upload image error', $fileArray);
        }
  	}
  	echo 'success';
  	$productInstance->updateProductFiles($pid, $files_info);
  }
}