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
   * 获得某一个商品全部的平均分数和星级的显示HTML
   */
  public function getaveragestars($pid = 0)
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
    /*$fiveStarsConfig = $this->getFiveStartsWidgetConfig();
    if ($fiveStarsConfig['status'] != 1) {
      return '';
    }*/
    $size = $fiveStarsConfig['iconSize'];
    $imagePath = $this->getIconStylePath();
    $imageUrl = url($imagePath.'/'.$fiveStarsConfig['iconStyle']);
    if (hasFunction('fivestartsAvgHtml')) {
      $output = callFunction('fivestartsAvgHtml', $size, $imageUrl, $starsNumber, $average);
    } else if (hasFunction('fivestartsHtml')) {
      $output = callFunction('fivestartsHtml', $size, $imageUrl, $starsNumber, $average);
    } else {
      $output = $this->getProductAverageStarsHTML($size, $imageUrl, $starsNumber, $average);
    }
    return $output;
  }


  public function _commentstars($instance, $cid = 0)
  {
    echo $this->getcommentstars($cid);
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
    $average = $info->grade;
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
      $output = $this->getProductAverageStarsHTML($size, $imageUrl, $starsNumber, $average);
    }
    return $output;
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
    $html .='<input type=\"hidden\" name=\"fivestarsvalue\" id=\"fivestarsvalue\" value = \"5\">';

    $script = 'var html="'. $html .'";var s=5;';
    $script .= 'document.getElementById("wiget_fivestars").innerHTML = html;';
    $script .= 'function fivestarsmouseover(i){var y=\'\';for(n=1;n<=5;n++){if(n<=i)y=\'0 0px\';else y=\'0 -'.$size.'px\';document.getElementById("fivestartsid"+n).style.backgroundPosition=y;}}';
    $script .= 'function setstarsvalue(i){s=i;document.getElementById("fivestarsvalue").value=i;}';
    $script .= 'function moveoutfivestars(){fivestarsmouseover(s)}';
    exit($script);
  }

  private function getProductAverageStarsHTML($size, $imageUrl, $starsNumber, $average)
  {
    $starsNumber = intval($starsNumber);
    if ($starsNumber < 1) {
      $starsNumber = 1;
    }
    if ($starsNumber > 5) {
      $starsNumber = 5;
    }
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
      $html .= '<div style="'. $this->imageGetStyle($imageUrl, $i<=$starsNumber ? 0 : $size*(-1), $size) .'" ></div>';
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