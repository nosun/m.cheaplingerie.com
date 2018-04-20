<?php
class Fivestars extends Widget_Abstract
{

  public function urls()
  {
    return array(
      'getform',
      'setproductgrade',
      'getaveragestars',
      'commentstars',
      'getcommentsbygrade',
    );
  }

  /**
   * 获取评级图标
   * @param $type, 当type为comment时是评论里面的评级
   */
  public function _getform($instance, $type = 'comment')
  {
    $fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      exit;
    }

    $size = $fiveStarsConfig['iconSize'];
    $imagePath = $this->getIconStylePath();
    $imageUrl = url($imagePath.'/'.$fiveStarsConfig['iconStyle']);

    if ($type == 'product') {
      $this->getProductFiveStarsForm($size, $imageUrl);
    } else {
      $this->getCommentFiveStarsForm($size, $imageUrl);
    }
  }

  /**
   * 设置指定商的评级数，完成后返回原地址
   */
  public function _setproductgrade($instance, $pid = 0, $grade = 0)
  {
    $productInstance = Product_Model::getInstance();
    if (!$productInstance->getProductInfo($pid)) {
      gotoBack();
    }
    $this->insertFiveStarsGrade($pid, 0, $grade);
    gotoBack();
  }

  public function _getaveragestars($instance, $pid = 0)
  {
    echo $this->getaveragestars($pid);
  }
  
  
  
  /**
   * 
   */
  public function getaveragegrade($pid = 0)
  {
    $fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      return '';
    }
    $info = $this->getAverageFiveStars($pid);
    if (!$info) {
      return '';
    }
    $average = round($info->grade_sum/$info->all_num, 2);
    $starsNumber = round($average);
    return $starsNumber;
  }

  /**
   * 获得某一个商品全部的平均分数和星级的显示HTML
   */
  public function getaveragestars($pid = 0, $small=true)
  {
    $fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      return '';
    }
    $info = $this->getAverageFiveStars($pid);
    
    $size = $fiveStarsConfig['iconSize'];
    $imagePath = $this->getIconStylePath();
    $imageUrl = url($imagePath.'/'.$fiveStarsConfig['iconStyle']);
    
    
    //if no one give stars.
    if (!$info) {
      return $this->_getDefaultStarsHTML($size, $imageUrl, $small);
    }
    $average = round($info->grade_sum/$info->all_num, 2);
    $starsNumber = round($average);
    /*$fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      return '';
    }*/

    if (hasFunction('fivestartsAvgHtml')) {
      $output = callFunction('fivestartsAvgHtml', $size, $imageUrl, $starsNumber, $average);
    } else if (hasFunction('fivestartsHtml')) {
      $output = callFunction('fivestartsHtml', $size, $imageUrl, $starsNumber, $average);
    } else {
      $output = $this->getProductStarsHTML($size, $imageUrl, $starsNumber, $small);
    }
    return $output;
  }


  public function _commentstars($instance, $cid = 0)
  {
    echo $this->getcommentstars($cid);
  }

  
  public function getcommentsbygrade($pid, $grade, $limit){
  	global $db;
  	$db->select('products.pid, comments.*, widget_fivestars.grade');
  	$db->from('products');
  	$db->join('products_comments', 'products.pid = products_comments.pid');
  	$db->join('comments', 'products_comments.cid = comments.cid');
  	$db->join('widget_fivestars', 'widget_fivestars.pid = products.pid and widget_fivestars.cid = comments.cid');
  	$db->where('products.pid = ', $pid);
  	if($grade){
  		$db->where('widget_fivestars.grade = ', $grade);
  	}
  	$db->where('comments.status = ', 1);
  	$db->orderby('widget_fivestars.grade DESC, timestamp DESC');
  	if($limit){
  		$db->limit($limit);
  	}
  	$result = $db->get();
  	$starComments = $result->all();
  	return $starComments;
  }
  
  
  /**
   * 获得某一个商品的某一评论的评级分数和星级的显示HTML
   */
  public function getcommentstars($cid = 0)
  {
    $fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      return '';
    }
    $info = $this->getCommentStarsInfo($cid);
    if (!$info) {
      return '';
    }
    $average = $info->grade;//this is not average, this is the total.
    $starsNumber = $average;
    $fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      return '';
    }
    $size = $fiveStarsConfig['iconSize'];
    $imagePath = $this->getIconStylePath();
    $imageUrl = url($imagePath.'/'.$fiveStarsConfig['iconStyle']);
    if (hasFunction('fivestartsCommentHtml')) {
      $output = callFunction('fivestartsCommentHtml', $size, $imageUrl, $starsNumber, $average);
    } elseif (hasFunction('fivestartsHtml')) {
      $output = callFunction('fivestartsHtml', $size, $imageUrl, $starsNumber, $average);
    } else {
      $output = $this->getProductStarsHTML($size, $imageUrl, $starsNumber, true);
    }
    return $output;
  }
  
  public function getcommentstarsGrade($cid = 0)
  {
  	$fiveStarsConfig = $this->getFiveStartsWidgetConfig();
  	if ($fiveStarsConfig['status'] != 1) {
  		return '';
  	}
  	$info = $this->getCommentStarsInfo($cid);

  	return $info;
  }
  

  private function getProductFiveStarsForm($size, $imageUrl)
  {
    $html  = '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(1)\" onclick=\"setstarsvalue(1)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid1\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(2)\" onclick=\"setstarsvalue(2)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid2\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(3)\" onclick=\"setstarsvalue(3)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid3\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(4)\" onclick=\"setstarsvalue(4)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid4\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, $size*(-1), $size) .'\" onmouseover=\"fivestarsmouseover(5)\" onclick=\"setstarsvalue(5)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid5\"></div>';
    $script = 'var html="'. $html .'";var s=4;';
    $script .= 'document.getElementById("wiget_fivestars").innerHTML = html;';
    $script .= 'function fivestarsmouseover(i){var y=\'\';for(n=1;n<=5;n++){if(n<=i)y=\'0 0px\';else y=\'0 -'.$size.'px\';document.getElementById("fivestartsid"+n).style.backgroundPosition=y;}}';
    $script .= 'function setstarsvalue(i){var pid=100;var path="/widget/fivestars/setproductgrade/"+pid+"/"+i; location.href=path;}';
    $script .= 'function moveoutfivestars(){fivestarsmouseover(s)}';
    exit($script);
  }

  private function getCommentFiveStarsForm($size, $imageUrl)
  {
    $html  = '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(1)\" onclick=\"setstarsvalue(1)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid1\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(2)\" onclick=\"setstarsvalue(2)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid2\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(3)\" onclick=\"setstarsvalue(3)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid3\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(4)\" onclick=\"setstarsvalue(4)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid4\"></div>';
    $html .= '<div style=\"'. $this->imageGetStyle($imageUrl, 0, $size) .'\" onmouseover=\"fivestarsmouseover(5)\" onclick=\"setstarsvalue(5)\" onmouseout=\"moveoutfivestars()\" id=\"fivestartsid5\"></div>';
    $html .='<input type=\"hidden\" name=\"ratings\" id=\"ratings\" value = \"5\">';

    $script = 'var html="'. $html .'";var s=5;';
    $script .= 'document.getElementById("wiget_fivestars").innerHTML = html;';
    $script .= 'function fivestarsmouseover(i){var y=\'\';for(n=1;n<=5;n++){if(n<=i)y=\'0 0px\';else y=\'0 -'.$size.'px\';document.getElementById("fivestartsid"+n).style.backgroundPosition=y;}}';
    $script .= 'function setstarsvalue(i){s=i;document.getElementById("ratings").value=i;}';
    $script .= 'function moveoutfivestars(){fivestarsmouseover(s)}';
    exit($script);
  }
  
  
  
  private function _getDefaultStarsHTML($size, $imageUrl, $small = false){
      $html = '';
    if(strpos($imageUrl, '_inone.png')){
      if($small == true){
        $html .= '<span class="star starS5"></span>';
      }else{
        $html .= '<span class="star starB5"></span>';
      }
    }else{
      for ($i = 1; $i <= 5; $i++) {
        $html .= '<div style="'. $this->imageGetStyle($imageUrl, $i<=5 ? 0 : $size*(-1), $size) .'" ></div>';
      }
    }
    return $html;
  }

  private function getProductStarsHTML($size, $imageUrl, $starsNumber, $small = false)
  {
    $starsNumber = intval($starsNumber);
    if ($starsNumber < 1) {
      $starsNumber = 1;
    }
    if ($starsNumber > 5) {
      $starsNumber = 5;
    }
    $html = '';
    if(strpos($imageUrl, '_inone.png')){
      if($small == true){
        $html .= '<span class="star starS'.$starsNumber.'"></span>';
      }else{
        $html .= '<span class="star starB'.$starsNumber.'"></span>';
      }
    }else{
      for ($i = 1; $i <= 5; $i++) {
        $html .= '<div style="'. $this->imageGetStyle($imageUrl, $i<=$starsNumber ? 0 : $size*(-1), $size) .'" ></div>';
      }
    }
    return $html;
  }

  private function imageGetStyle($imageUrl, $backgroundY, $size = 26)
  {
    $style = 'width:'.$size.'px;height:'.$size.'px;margin:0;padding:0;float:left;background-attachment:fixed;'
              .'background:url(\''.$imageUrl.'\') no-repeat;background-position:0 '.$backgroundY.'px;cursor:pointer;';
    return $style;
  }

  /**
   * 设置产品级别
   * @param obj $instance
   * @param INT $pid, 产品ID
   * @param INT $cid, 所属评论ID
   * @param INT $grade, 评论等级
   */
  public function setstars($pid = 0, $cid = 0, $grade = 0)
  {
    $productInstance = Product_Model::getInstance();
    if (!$productInstance->getProductInfo($pid)) {
      return false;
    }
    $this->insertFiveStarsGrade($pid, $cid, $grade);
    return true;
  }
  
  
  public function getStars($pid = 0){
    global $db;
    $sql = 'SELECT grade, COUNT(cid) as c FROM widget_fivestars where pid ='. $db->escape($pid) . ' Group BY grade desc;';
    $result = $db->query($sql);
    $arr = $result->all();
    $grades = array(5=>0, 4=>0, 3=>0, 2=>0, 1=>0);
    foreach($arr as $k=> $v){
      $grades[$v->grade] = $v->c;
    }
    return $grades;
  }
  
  


  /**
   * 显示编辑界面
   * 选择样式，是否开启等信息
   */
  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $fiveStartsWidgetInfo = $this->getFiveStartsWidgetInfo();
    $instance->view->render('../plugins/widget/fivestars/info.phtml', array(
      'info' => $fiveStartsWidgetInfo,
    ));
  }

  /**
   * 保存配置结果
   */
  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    $iconStyle = $_POST['iconStyle'];
    $status = $_POST['status'];
    $iconInfo = $this->getIconImageInfo($iconStyle);
    if ($iconInfo && isset($iconInfo[0])) {
      $iconSize = $iconInfo[0];
    } else {
      setMessage('Invalid info post.', 'error');
      gotoUrl('admin/site/widgetedit/fivestars');
    }
    $this->setFiveStartsWidgetConfig($iconStyle, $status, $iconSize);
    setMessage('Change successful.');
    gotoUrl('admin/site/widgetedit/fivestars');
  }

  public function install()
  {
    $this->createTable();
  }

//FUNC---------------------------------------------------------------------------

  private function getFiveStartsWidgetInfo()
  {
    $info = array();
    $info['iconList'] = $this->getIconStyleList();
    $info['iconImagePath'] = $this->getIconStylePath();
    $config = $this->getFiveStartsWidgetConfig();
    $info = array_merge($info, $config);
    return (object)$info;
  }


  private function getFiveStartsWidgetConfig()
  {
    $config['iconStyle'] = Bl_Config::get('widgetFiveStartsStyle', 'stars1.png');
    $config['status'] = Bl_Config::get('widgetFiveStartsStatus', 1);
    $config['iconSize'] = Bl_Config::get('widgetFiveStartsIconSize', 26);
    return $config;
  }

  private function setFiveStartsWidgetConfig($iconStyle, $status, $iconSize)
  {
    Bl_Config::set('widgetFiveStartsStyle', $iconStyle);
    Bl_Config::set('widgetFiveStartsStatus', $status);
    Bl_Config::set('widgetFiveStartsIconSize', $iconSize);
    Bl_Config::save();
  }


  /**
   * 获得图标风格的列表
   */
  private function getIconStyleList()
  {
    $iconList = array();
    $iconStylePath = DOCROOT.'/'.$this->getIconStylePath();

    if (!file_exists($iconStylePath)) {
      return false;
    }
    $handle = opendir($iconStylePath);

    while (false !== ($file = readdir($handle))) {
      if ($file == '..' || $file == '.') {
        continue;
      }
      $filePath = $iconStylePath.'/'.$file;
      if (is_file($filePath)) {
        $iconList[$file] = getimagesize($filePath);
      }
    }
    return $iconList;
  }

  private function getIconImageInfo($file)
  {
    $iconStylePath = DOCROOT.'/'.$this->getIconStylePath();
    $filePath = $iconStylePath.'/'.$file;
    if (!file_exists($filePath) || !is_file($filePath)) {
      return false;
    }
    return getimagesize($filePath);
  }

  private function getIconStylePath()
  {
    return 'images/widget_fivestars';;
  }

//DB---------------------------------------------------------------------------

  private function getAverageFiveStars($pid)
  {
    global $db;
    $cacheId = 'productStart-' . $pid;
    if($cache = cache::get($cacheId)) {
      $info = $cache->data;
    } else {
      $sql = 'SELECT count(`create`) AS all_num, SUM(grade) AS grade_sum FROM widget_fivestars WHERE pid="'.$db->escape($pid).'" GROUP BY pid';
      $result = $db->query($sql);
      $info = $result->row();
      cache::save($cacheId, $info);
    }
    return $info;
  }

  private function getCommentStarsInfo($cid)
  {
    global $db;
    $db->select('*');
    $db->where('cid', $cid);
    $result = $db->get('widget_fivestars');
    return $result->row();
  }

  private function insertFiveStarsGrade($pid, $cid, $grade)
  {
    global $db, $user;
    $set = array(
      'pid' => $pid,
      'cid' => $cid,
      'uid' => $user->uid,
      'grade' => $grade,
      'create' => time(),
    );
    $db->insert('widget_fivestars', $set);
  }
  
  private function getStarsGrade($pid, $cid){
    global $db, $user;
    $db->select('COUNT(grade)');
    $db->where('cid', $cid);
    $db->where('pid', $pid);
    $result = $db->get('widget_fivestars');
    
    $sql = 'SELECT grade from widget_fivestars where cid = "' . $db->escape($cid) . 'pid = "' . $db->escape($pid) . '"';
    $result = $db->query($sql);
    $arr = $result->row();
    return $arr;
  }
  

  private function createTable()
  {
    global $db;
    $sql = 'CREATE TABLE IF NOT EXISTS `widget_fivestars` (
              `pid` int(11) NOT NULL,
              `cid` int(11) NOT NULL DEFAULT \'0\',
              `uid` int(11) NOT NULL,
              `grade` int(11) NOT NULL,
              `create` int(11) NOT NULL,
              KEY `pid` (`pid`,`cid`,`uid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';

    $db->exec($sql);
  }
  
  
  
}