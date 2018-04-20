<?php
class Script_Controller extends Bl_Controller
{
  private $_siteInstance;
  
  public function init ()
  {
    $this->_siteInstance = Site_Model::getInstance();
  }
  
  public function indexAction ()
  {
    goto404(t('forbidden'));
  }
  
  public function getadimgAction ($token, $tid=0, $width = null, $height = null)
  {
  	
  	if (!$token) {
  		return false;
  	}
  	if (is_numeric($token)){
  		$adphotoInfo = $this->_siteInstance->getadphotoInfo($token, $tid);
  		if (!$adphotoInfo) {
  		  $adphotoInfo = $this->_siteInstance->getadphotoInfo($token);
  		}
  	} else {
  	  $adphotoInfo = $this->_siteInstance->getadphotoInfoByScriptId($token, $tid);
  	  if (!$adphotoInfo) {
        $adphotoInfo = $this->_siteInstance->getadphotoInfoByScriptId($token);
      }
  	}
  	if (isset($adphotoInfo) && $adphotoInfo && $adphotoInfo->visible == 1) {
  		$adphotoInfo->width = isset($width) ? $width : $adphotoInfo->width;
  		$adphotoInfo->height = isset($height) ? $height : $adphotoInfo->height;
  		if (strpos($adphotoInfo->filepath, 'http') === false) {
    		if (file_exists(DOCROOT . '/' . $adphotoInfo->filepath)) {
    		  $filepath = url($adphotoInfo->filepath);
    		} else if (file_exists(DOCROOT . '/' . 'images/' . $adphotoInfo->filepath)) {
    		  $filepath = url('images/' . $adphotoInfo->filepath);
    		} else {
    		  $filepath = "";
    		}
  		} else {
  		  $filepath = $adphotoInfo->filepath;
  		}
  		if ($adphotoInfo->type == 1) {
  			if (strpos($adphotoInfo->filepath, 'http') !== false) {
  				echo 'document.write(\'<a href="'.$adphotoInfo->url.'" target="'.$adphotoInfo->target.'"><img src="'. $adphotoInfo->filepath .'" width="'. $adphotoInfo->width .'" height="'. $adphotoInfo->height .'"></a>\');';
  		  } else {
  		  	echo 'document.write(\'<a href="'.$adphotoInfo->url.'" target="'.$adphotoInfo->target.'"><img src="'. $filepath .'" width="'. $adphotoInfo->width .'" height="'. $adphotoInfo->height .'"></a>\');';
  		  }
  		} elseif ($adphotoInfo->type == 2 ) {
  			$str = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,19,0" width="'. $adphotoInfo->width .'" height="'. $adphotoInfo->height .'">';
        $str .= '<param name="movie" value="/images/'.$adphotoInfo->filepath.'" >';
        $str .= '<param name="quality" value="high" >';
        $str .= '<embed src="/images/'.$adphotoInfo->filepath.'" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" width="'. $adphotoInfo->width .'" height="'. $adphotoInfo->height .'"></embed></object>';
        echo "document.write('$str')";
  		} elseif ($adphotoInfo->type == 3 ) {
        $str = '<embed width="'. $adphotoInfo->width .'" height="'. $adphotoInfo->height .'" transparentatstart=true animationatstart=false autostart=true autosize=false volume=100 displaysize=0 showdisplay=true showstatusbar=true showcontrols=true showaudiocontrols=true showtracker=true showpositioncontrols=true balance=true src="'. $filepath .'"></embed>';
        $str = '<embed src="/images/googleplayer.swf?videoUrl='. $filepath .'&thumbnailUrl=&playerMode=normal" type="application/x-shockwave-flash" wmode="transparent" quality="high" width="'. $adphotoInfo->width .'" height="'. $adphotoInfo->height .'" autostart="true"></embed>';
        echo "document.write('$str')";
      }
	  } else {
  		goto404(t('Source image') . '<em>' . $source . '</em>' . t('not found.'));
  	}
  }
  
  public function getcatchadimgAction($token)
  {
  	if ($adphotoInfo = $this->_siteInstance->getadphotoInfo($token)) {
      $source = '/images/' . $adphotoInfo->filepath;
	    if (!is_file(DOCROOT . $source)) {
	      goto404(t('Source image') . '<em>' . $source . '</em>' . t('not found.'));
	    }
	    $presets = array(
	      'type' => 'resize',
	      'width' => $adphotoInfo->width,
	      'height' => $adphotoInfo->height
	    );
	    Bl_Core::loadLibrary('imageapi');
	    $imageapi = new Bl_Imageapi();
	    $imageapi->process(DOCROOT . $source, $presets);
	    $imageapi->output('/images/cache/adphoto_album/' . $adphotoInfo->filepath);
    }
  }
}
