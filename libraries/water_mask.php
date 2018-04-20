<?php
$water_mask_image_name = "water_mask.png";
function watermark_image($oldimage_name, $new_image_name){
	//resize water mask image to fit the source image
	global $water_mask_image_name;
	list($owidth,$oheight) = getimagesize($oldimage_name);
	list($water_mask_width, $water_mask_height) = getimagesize($water_mask_image_name);
	$water_mask_image = imagecreatefrompng($water_mask_image_name);
	$dest_water_mask_height = $owidth * $oheight / $water_mask_width;
	$dest_water_mask_width = $owidth;
	$dest_water_mask_image = imagecreate($dest_water_mask_width, $dest_water_mask_height);
	imagecopyresized($dest_water_mask_image, $water_mask_image, 0, 0, 0, 0, $dest_water_mask_width, $dest_water_mask_height, $water_mask_width, $water_mask_height);
	
	$img_src = imagecreatefromjpeg($oldimage_name);
	$dest_water_mask_x = 0;
	$dest_water_mask_y = ($oheight - $dest_water_mask_height) / 2;
	imagecopy($img_src, $dest_water_mask_image, $dest_water_mask_x, $dest_water_mask_y, 0, 0, $dest_water_mask_width, $dest_water_mask_height);
	
	imagejpeg($img_src,$new_image_name, 100);
	
	imagedestroy($water_mask_image);
	imagedestroy($dest_water_mask_image);
	imagedestroy($image_src);
	return true;
}

$source_image_list = glob('*.jpg');

if (!is_dir('water_mask')) {
	mkdir('water_mask');
}
foreach ($source_image_list as $source_image) {
	$dest_image_name = 'water_mask/' . $source_image;
	if (watermark_image($source_image, $dest_image_name)) {
		echo $source_image . ' make water mask success.';
	}
}