<?php
final class Bl_Imageapi
{
  private $_imageinfo;
  private $_preset;
  private $_processIm;
  private $_destImageInfo;
  
  private function scale($im, $width, $height)
  {
    $aspect = $this->_imageinfo[1] / $this->_imageinfo[0];
    if ($aspect < $height / $width) {
      $height = $width * $aspect;
    }
    else {
      $width = $height / $aspect;
    }
    $this->resize($im, $width, $height);
  }
  /*scale by width size*/
  private function scaleX($im, $width, $height, $fillbg = true)
  {
    $aspect = $this->_imageinfo[1] / $this->_imageinfo[0];
    $srcHeight = $width * $aspect;
    $this->resize($im, $width, $height, $width, $srcHeight, $fillbg);
  }
  /*scale by height size*/
  private function scaleY($im, $width, $height, $fillbg = true)
  {
    $aspect = $this->_imageinfo[1] / $this->_imageinfo[0];
    $destWidth = $height * $aspect;
    $this->resize($im, $width, $height, $srcWidth, $height, $fillbg);
  }

  private function crop($im, $width, $height)
  {
    if ($this->_imageinfo[0] > $width) {
      $x = ($this->_imageinfo[0] - $width) / 2;
    } else {
      $x = 0;
      if ($this->_imageinfo[0] < $width) {
        $width = $this->_imageinfo[0];
      }
    }
    if ($this->_imageinfo[1] > $height) {
      $y = ($this->_imageinfo[1] - $height) / 2;
    } else {
      $y = 0;
      if ($this->_imageinfo[1] < $height) {
        $height = $this->_imageinfo[1];
      }
    }
    if (!isset($this->_processIm)) {
      $this->_processIm = imagecreatetruecolor($width, $height);
    }
    imagecopy($this->_processIm, $im, 0, 0, $x, $y, $width, $height);
  }

  private function resize($im, $destWidth, $destHeight, $srcWidth=null, $srcHeight=null, $fillbg=false)
  {
   if (!isset($this->_processIm)) {
      $this->_processIm = imagecreatetruecolor($destWidth, $destHeight);
    }
    
    if(!isset($srcWidth)){
    	$srcWidth = $destWidth;
    }
    if(!isset($srcHeight)){
    	$srcHeight = $destHeight;
    }
    if($srcWidth > $destWidth){
    	$destWidth = $srcWidth;
    }
    if($srcHeight > $destHeight){
    	$destHeight = $srcHeight;
    }
    if($fillbg){
    	$backgroundColor = imagecolorallocate($this->_processIm, 255, 255, 255);
    	imagefill($this->_processIm, 0, 0, $backgroundColor);
    }
    imagecopyresampled($this->_processIm, $im, ($destWidth - $srcWidth)/2, ($destHeight - $srcHeight)/2, 0, 0, $srcWidth, $srcHeight, $this->_imageinfo[0], $this->_imageinfo[1]);
    $this->_destImageInfo = array($destWidth, $destHeight);
  }

  private function waterMark($im, $width = null, $height = null)
  {
  	$water_mark_image_name = TPLPATH . '/' . Bl_Config::get('template', 'default') . '/images/water_mark.png';
  	$owidth = isset($width) ? $width : $this->_imageinfo[0];
  	$oheight= isset($height) ? $height : $this->_imageinfo[1];
  	list($water_mark_width, $water_mark_height) = getimagesize($water_mark_image_name);
  	$water_mark_image = imagecreatefrompng($water_mark_image_name);
  	$dest_water_mark_width = $owidth - 20;
  	$dest_water_mark_height = ceil($dest_water_mark_width * $water_mark_height / ($water_mark_width));
  	$dest_water_mark_image = imagecreate($dest_water_mark_width, $dest_water_mark_height);
  	imagecopyresampled($dest_water_mark_image, $water_mark_image, 0, 0, 0, 0, $dest_water_mark_width, $dest_water_mark_height, $water_mark_width, $water_mark_height);
  
  	$dest_water_mark_x = ceil(($owidth - $dest_water_mark_width) / 2);
  	$dest_water_mark_y = ceil(($oheight - $dest_water_mark_height) / 2);
  	imagecopy($im, $dest_water_mark_image, $dest_water_mark_x, $dest_water_mark_y, 0, 0, $dest_water_mark_width, $dest_water_mark_height);
  	if (!isset($this->_processIm)) {
  		$this->_processIm = imagecreatetruecolor($owidth, $oheight);
  	}
  	imagecopy($this->_processIm, $im, 0, 0, 0, 0, $owidth, $oheight);
  	 
  	imagedestroy($water_mark_image);
  	imagedestroy($dest_water_mark_image);
  }
  
  private function fill($im, $destWidth, $destHeight)
  {
  	$owidth = $this->_imageinfo[0];
  	$oheight= $this->_imageinfo[1];
  	
  	if (!isset($this->_processIm)) {
  		$this->_processIm = imagecreatetruecolor($destWidth, $destHeight);
  	}
  	
  	if($oheight > $owidth){   //图片为细高的
  		$mw =  ceil($owidth * $destHeight / $oheight);
  		$x = ceil(($destWidth -$mw ) / 2);
  		
  		// 白色背景填充整张图
  		$backgroundColor = imagecolorallocate($this->_processIm, 255, 255, 255);
  		imagefill($this->_processIm, 0, 0, $backgroundColor);
  		
  		//  将原图的整张 (0,0,$owidth,$oheight) 按照新的大小($mw ,$destHeight ) 复制到新的位置 ($x , 0 )
  		imagecopyresampled($this->_processIm, $im, $x, 0, 0, 0, $mw, $destHeight, $owidth, $oheight);

  		$this->_destImageInfo = array($destWidth, $destHeight);
  	}
  	else{  //图片为矮宽
  		$mh =  ceil($oheight * $destWidth / $owidth);
  		$y = ceil(($destHeight -$mh ) / 2);
  		
  		// 白色背景填充整张图
  		$backgroundColor = imagecolorallocate($this->_processIm, 255, 255, 255);
  		imagefill($this->_processIm, 0, 0, $backgroundColor);
  		
  		//  将原图的整张 (0,0,$owidth,$oheight) 按照新的大小($destWidth ,$mh ) 复制到新的位置 (0 ,$y )
  		imagecopyresampled($this->_processIm, $im, 0, $y, 0, 0, $destWidth, $mh, $owidth, $oheight);
  		
  		$this->_destImageInfo = array($destWidth, $destHeight);
  	}
  	
  }
  
  private function grayscale()
  {
    imagefilter($this->_processIm, IMG_FILTER_GRAYSCALE);
  }

  public function process($source, $preset)
  {
    if (!isset($preset['width']) || !isset($preset['height'])) {
      goto404('Imagecache preset setting is invalid.');
    }
    $imageinfo = getimagesize($source);
    if ($imageinfo[2] == IMAGETYPE_GIF) {
      $im = imagecreatefromgif($source);
    } else if ($imageinfo[2] == IMAGETYPE_JPEG) {
      $im = imagecreatefromjpeg($source);
    } else if ($imageinfo[2] == IMAGETYPE_PNG) {
      $im = imagecreatefrompng($source);
    } else {
      goto404('Source image format is invalid.');
    }
    $this->_imageinfo = $imageinfo;
    $preset['type'] = (isset($preset['type']) && in_array(strtolower($preset['type']), array('crop', 'resize', 'scale', 'scalex', 'scaley', 'fill', 'water_mark', 'resize_water_mark'))) ?
      strtolower($preset['type']) : 'scale';
    $this->_preset = $preset;
    switch ($preset['type']) {
      case 'crop':
        $this->crop($im, $preset['width'], $preset['height']);
        break;
      case 'resize':
        $this->resize($im, $preset['width'], $preset['height']);
        break;
      case 'scale':
        $this->scale($im, $preset['width'], $preset['height']);
        break;
      case 'scalex':
        $this->scaleX($im, $preset['width'], $preset['height']);
        break;
      case 'scaley':
        $this->scaleY($im, $preset['width'], $preset['height']);
        break;
      case 'water_mark':
      	$this->waterMark($im);
      	break;
      case 'resize_water_mark':
      	$this->scale($im, $preset['width'], $preset['height']);
      	$tmp_img = imagecreatetruecolor($this->_destImageInfo[0], $this->_destImageInfo[1]);
      	imagecopy($tmp_img, $this->_processIm, 0, 0, 0, 0, $this->_destImageInfo[0], $this->_destImageInfo[1]);
      	$this->waterMark($tmp_img, $this->_destImageInfo[0], $this->_destImageInfo[1]);
      	imagedestroy($tmp_img);
      	break;
      case 'fill':
      	$this->fill($im, $preset['width'], $preset['height']);
      	break;
    }
    if (isset($preset['grayscale'])) {
      $this->grayscale();
    }
    imagedestroy($im);
  }

  public function output($destination)
  {
    if (!isset($this->_imageinfo) || !isset($this->_processIm)) {
      return;
    }
    makedir(dirname($destination), 706);
    ob_end_clean();
    header('Content-type: ' . $this->_imageinfo['mime']);
    switch ($this->_imageinfo[2]) {
      case IMAGETYPE_GIF:
        imagegif($this->_processIm, DOCROOT . $destination);
        imagegif($this->_processIm);
        break;
      case IMAGETYPE_JPEG:
        $quality = isset($this->_preset['quality']) ? intval($this->_preset['quality']) : 100;
        imagejpeg($this->_processIm, DOCROOT . $destination, $quality);
        imagejpeg($this->_processIm, null, $quality);
        break;
      case IMAGETYPE_PNG:
        imagepng($this->_processIm, DOCROOT . $destination);
        imagepng($this->_processIm);
        break;
    }
  }

  public function close()
  {
    if (isset($this->_processIm)) {
      imagedestroy($this->_processIm);
    }
  }
}