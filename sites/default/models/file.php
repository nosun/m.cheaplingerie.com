<?php
class File_Model extends Bl_Model
{
  /**
   * @return File_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

	/**
   * 获取文件信息
   * @param int $fid 文件ID
   * @return object
   */
  public function getFileInfo($fid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$fid])) {
      $result = $db->query('SELECT * FROM files WHERE fid = ' . $fid);
      $list[$fid] = $result->row();
    }
    return $list[$fid];
  }

  /**
   * 新增文件
   * @param string $fileKey 表单文件域名
   * @param array $post 文件信息
   * @return object
   */
  public function insertFile($fileKey, $post = array(), $delta = 0)
  {
    global $db, $user;
    $file = $this->uploadFile($fileKey, $post, $delta);
    if ($file) {
      $set = array(
        'uid' => $user->uid,
        'ip' => ipAddress(),
        'filename' => $file->filename,
        'filepath' => $file->filepath,
        'filesize' => $file->filesize,
        'filemime' => $file->filemime,
        'alt' => isset($post['alt']) ? $post['alt'] : '',
        'timestamp' => TIMESTAMP,
      );
      $db->insert('files', $set);
      $fid = $db->lastInsertId();
      if ($fid) {
        $set['fid'] = $fid;
        $file = (object)$set;
      } else {
        $file = false;
      }
    }
    return $file;
  }

  /**
   * 上传文件
   * @param string $fileKey 表单文件域名
   * @param array $post 文件信息
   * @return object
   */
  public function uploadFile($fileKey, $post = array(), $delta = 0)
  {
    if (!isset($_FILES[$fileKey])) {
      return false;
    }
    if (is_array($_FILES[$fileKey]['name'])) {
      if (!isset($_FILES[$fileKey]['name'][$delta])) {
        return false;
      }
      $name = $_FILES[$fileKey]['name'][$delta];
      $type = $_FILES[$fileKey]['type'][$delta];
      $error = $_FILES[$fileKey]['error'][$delta];
      $size = $_FILES[$fileKey]['size'][$delta];
      $tmpName = $_FILES[$fileKey]['tmp_name'][$delta];
    } else {
      $name = $_FILES[$fileKey]['name'];
      $type = $_FILES[$fileKey]['type'];
      $error = $_FILES[$fileKey]['error'];
      $size = $_FILES[$fileKey]['size'];
      $tmpName = $_FILES[$fileKey]['tmp_name'];
    }
    if ($error == 0 && $size > 0) {
      $destination = callFunction('uploadPath', isset($post['type']) ? $post['type'] : '');
      if (!is_null($destination) && $destination != '' && substr($destination, -1) != '/') {
        $destination .= '/';
      }
      $extName = '.' . pathinfo($name, PATHINFO_EXTENSION);
      if (isset($post['name'])) {
        $filepath = $destination . $post['name'] . $extName;
      } else {
        $attachment = Bl_Config::get('attachment.setting', array());
        if ($attachment && $attachment['name'] == 2) {
          $filepath = $destination . $name;
        } else {
          $filepath = $destination . randomString(32) . $extName;
        }
      }
      $destination = '/images/' . $filepath;
      makedir(dirname($destination), 0706);
      
      if (move_uploaded_file($tmpName, DOCROOT . $destination)) {
        $file = new stdClass();
        $file->filename = $name;
        $file->filepath = $filepath;
        $file->filesize = $size;
        $file->filemime = $type;
        return $file;
      }
    }
    return false;
  }

  public function updateFile($fid, $post)
  {

  }

  /**
   * 删除文件
   * @param int $fid 文件ID
   * @return boolean
   */
  public function deleteFile($fid)
  {
    global $db;
    if ($file = $this->getFileInfo($fid)) {
      $db->exec('DELETE FROM files WHERE fid = ' . $fid);
      $affected = $db->affected();
      if ($affected) {
        $filename = DOCROOT . '/images/' . $file->filepath;
        if (is_file($filename)) {
          unlink($filename);
        }
        // TODO 删除缩略图?
      }
      return (bool)$affected;
    }
    return false;
  }
}
