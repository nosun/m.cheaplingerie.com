<?php
class Admin_Default_Controller extends Bl_Controller
{
  public static function __permissions()
  {
    return array(
      'administrator page',
    );
  }

  public function indexAction()
  {
    if (!access('administrator page')) {
      //goto403('<a href="' . url('admin/login') . '">登录</a>');
      gotoUrl('admin/login');
    }
    $siteInstance = Site_Model::getInstance();
  	$currentVersion = Bl_Config::get('update.version', 0);
    $updateVersions = $siteInstance->getUpdateVersions($currentVersion);
    if(! empty($updateVersions)){
	   $currentVersion = $siteInstance->runUpdate($currentVersion);
	   Bl_Config::set('update.version', $currentVersion);
	   Bl_Config::save();
    }
    gotoUrl('admin/product');
  }

  public function rebuildProductSphinxKeyAction()
  {
    if (!access('super')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    global $db;
    $db->exec('UPDATE products p SET p.sphinx_key = ""');
    $db->exec('UPDATE products p, terms t SET p.sphinx_key = t.name WHERE p.brand_tid = t.tid');
    $affected1 = $db->affected();
    $db->exec('UPDATE products p, terms t SET p.sphinx_key = CONCAT(p.sphinx_key, " ", t.name) WHERE p.directory_tid = t.tid');
    $affected2 = $db->affected();
    echo 'REBUILD FINISHED.<br>';
    echo 'REBUILD ' . max($affected1, $affected2) . ' ROWS.';
  }
}
