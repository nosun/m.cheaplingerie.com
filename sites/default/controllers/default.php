<?php
class Default_Controller extends Bl_Controller
{
  public function indexAction()
  {

  $ipAdd = ipAddress();
  
  
  //if(isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'babydoll-lingerie.net')){
	/*  if(ip_in_range($ipAdd, '64.233.160.0', '64.233.191.255')
	  	||ip_in_range($ipAdd, '66.102.0.0', '66.102.15.255')
	  	||ip_in_range($ipAdd, '66.249.64.0', '66.249.95.255')
	  	||ip_in_range($ipAdd, '72.14.192.0', '72.14.255.255')
	  	||ip_in_range($ipAdd, '74.125.0.0', '74.125.255.255')
	  	||ip_in_range($ipAdd, '209.85.128.0', '209.85.255.255')
	  	||ip_in_range($ipAdd, '216.239.32.0', '216.239.63.255')
	  	||ip_in_range($ipAdd, '66.175.219.250', '66.175.219.250')){
	  		echo file_get_contents('staticcontent');
	  		exit;
	  	}
	  	*/
  //}
  /*
  if(isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'babydoll-lingerie.net') && in_array(ipAddress(), $special_ips)){
  		echo file_get_contents('staticcontent');
  		exit;
  }
  */
  	$termchilds = Taxonomy_Model::getInstance()->getTermsList(3);
  	$this->view->assign("lists", $termchilds);
  	$this->view->render('index.phtml');
  }

  public function imagecacheAction()
  {
    $args = func_get_args();
    $presetName = array_shift($args);
    if (!isset($presetName)) {
      goto404(t('Imagecache preset name not set'));
    }
    if (empty($args)) {
      goto404(t('Source image not set'));
    }
    $presets = array();
    if (function_exists('hook_imagecachePresets')) {
      $presets += hook_imagecachePresets();
    }
    if (function_exists('imagecachePresets')) {
      $presets += imagecachePresets();
    }

    if (!isset($presets[$presetName]) || !is_array($presets[$presetName])) {
      goto404(t('Imagecache preset name') .' <em>' . $presetName . '</em>'. t('not found'));
    }
    $filePath = implode('/' , $args);
    $source = '/images/' . $filePath;
    if (!is_file(DOCROOT . $source)) {
      goto404(t('Source image') . '<em>' . $source . '</em>'. t('not found'));
    }
    Bl_Core::loadLibrary('imageapi');
    $imageapi = new Bl_Imageapi();
    $imageapi->process(DOCROOT . $source, $presets[$presetName]);
    $imageapi->output('/images/cache/' . $presetName . '/' . $filePath);
    $imageapi->close();
    exit;
  }

  public function testpaymentAction($payment = '')
  {
    $paymentInstance = Payment_Model::getInstance();
    if ($payment && ($instance = $paymentInstance->getPaymentInstance($payment)) && ($paymentInfo = $paymentInstance->getPaymentInfo($payment)) && $paymentInfo->status) {
      $order = array(
        'oid' => mt_rand(1, 1000),
        'number' => date('Ymd') . randomString(4),
        'pay_amount' => mt_rand(1000, 5000) / 100,
      );
      $info = $paymentInstance->getOrderPaymentInfo((object) $order);
      echo $instance->getSubmitForm($info);
    } else {
      echo '<h1>Can not find the payment</h1>';
    }
  }

  public function changeCurrencyAction($currency)
  {
    if ($currency) {
      $_SESSION['currency'] = $currency;
    }
    $reffer_url = $_SERVER["HTTP_REFERER"];
    header("Location: ".$reffer_url);
    exit;
  }

  public function changeLanguage()
  {

  }

  public function robotsAction()
  {
    $robots = Bl_Config::get('robots.txt');
    if (!isset($robots)) {
      $robots = file_get_contents(SITESPATH . '/default/plugins/robots.txt');
    }
    header('Content-Type: text/plain');
    echo $robots;
    exit;
  }

  public function sitemapAction($type = null, $page = null)
  {
    if (!isset($type)) {
      $xml = widgetCallFunction('sitemapxml', 'getXML');
    } else {
      $xml = widgetCallFunction('sitemapxml', 'getXML2', $type, $page);
    }
    if (!$xml) {
      goto404(t('sitemap.xml not found'));
    } else {
      echo $xml;
    }
  }
  
  function getFaviconAction()
  {
  	$template = Bl_Config::get('template');
  	
  	$name = TPLPATH .'/'.$template. '/images/favicon.ico';
  	$fp = fopen($name, 'rb');

	// send the right headers
	header("Content-Type: image/ico");
	header("Content-Length: " . filesize($name));

	// dump the picture and stop the script
	fpassthru($fp);
	exit;

  }
  
  function getRssAction()
  {
    $siteInfo = Bl_Config::get('siteInfo', array());
    $siteName = isset($siteInfo['sitename']) ? $siteInfo['sitename'] : 'Commercial websites';
    $productInstance = Product_Model::getInstance();
    $filter = array(
      'status' => 1,
      'orderby' => 'updated DESC',
    );
    $productList = $productInstance->getProductsList($filter, 1, 20);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL; 
    echo '<?xml-stylesheet type="text/css" href="'. url('default/getrss') .'"?>' . PHP_EOL; 
    echo '<rss version="2.0">' . PHP_EOL; 
    echo '<channel>' . PHP_EOL; 
    echo '<title>' . $siteName . '</title>' . PHP_EOL; 
    echo '<link>' . url('') . '</link>' . PHP_EOL; 
    echo '<description>Newest Goods</description>' . PHP_EOL;
    $i = 1; 
    foreach ($productList as $product) {
      echo '<item id="'. $i .'">'  . PHP_EOL; 
      echo '<title><![CDATA[' . $product->name . ']]></title>'  . PHP_EOL;  
      echo '<link>' . url($product->url) . '</link>'  . PHP_EOL;  
      echo '<description><![CDATA[' . strwidth(strip_tags($product->description), 500) . ']]></description>'  . PHP_EOL;  
      echo '<pubDate>' . date('Y-m-d H:i:s', $product->updated) . '</pubDate>'  . PHP_EOL;  
      echo '<lastBuildDate>Mon, 27 Dec 2010 02:13:17 GMT</lastBuildDate></item>'. PHP_EOL;
      $i++;
    }
    echo '</channel>'   . PHP_EOL; 
    echo '</rss>';
  }
}
