<?php
function uploadPath($type)
{
  $attachment = Bl_Config::get('attachment.setting', array());
  if ($type != '') {
      $type .= '/';
  }
  $dir = HOSTNAME . '/' . $type . date('Ym');
  if ($attachment) {
    if ($attachment['directory'] == 2) {
      $dir = HOSTNAME . '/' . $type . date('Y');
    } else if ($attachment['directory'] == 3) {
      $dir = HOSTNAME . '/' . $type;
    }
  }
  return $dir;
}

function imagecachePresets()
{
  return array(
    'admin_product_album' => array(
      'width' => 80,
      'height' => 80,
    ),
    'admin_term_album' => array(
      'width' => 120,
      'height' => 120,
    ),
    'taoawards_img' => array(
      'width' => 203,
      'height' => 112,
    )
  );
}

function hook_ping()
{
  global $db;
  $result = $db->query('SELECT COUNT(0) FROM widget_seotags st WHERE st.status = 1 AND st.ptag_id <> 0');
  $count = $result->one();
  $i = 0;
  for ( ; $count > 0; $count = $count - 5000) {
    $i++;
    $url = 'default/sitemap/seotag/' . $i;
    widgetCallFunction('sitemapxml', 'pingXML', $url);
  }
}