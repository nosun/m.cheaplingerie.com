<?php
$config = array();

$config['routers'] = array(
  'images/cache' => 'default/imagecache',
  'user/panel' => 'order/list',
  'search' => 'sphinx/get',
  'pitems' => 'sphinx/get',
  'itemfilter' => 'sphinx/skip',
  'articletype' => 'article/list',
  'browse' => 'product/browse',
  'review' => 'product/review',
  'browsefilter' => 'product/skip',
  'robots.txt' => 'default/robots',
  'sitemap.xml' => 'default/sitemap',
  'favicon.ico' => 'default/getFavicon'
);

$config['sites'] = array(

);

$config['debug'] = true;

$config['sphinx.server'] = '127.0.0.1';
$config['sphinx.port'] = 9312;

$config['undercarriageShow'] = 0;
$config['subdomains']= array();

$config['subdomain-support']= array('browse'=>'');