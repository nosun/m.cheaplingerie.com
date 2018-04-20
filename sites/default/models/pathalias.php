<?php
class Alias_Model extends Bl_Model
{
  /**
   * @return Order_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }
  
  public function checkAlias($src,$dest)
  {
   global $db;
   $result = $db->query('SELECT src FROM path_alias WHERE dest = "'.$db->escape($dest).'" ');
   $src_exist = $result->one();
   var_dump($src);exit;
   if(!empty($src_exist) && $src_exist != $src){
     return true;
   } else {
   	 return false;
   }
  }
  
  public function insertAlias($src, $dest){
  	global $db;
  	$db->insert('path_alias', array('src' => $src, 'dest' => $dest)) ;
    return $db->lastInsertId();
  }
  
  public function updateAlias($src, $dest){
    global $db;
    $db->delete('path_alias', array('src' => $src)) ;
    return $this->insertAlias($src, $dest);
  }
}