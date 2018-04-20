<?php
//瀹氫箟缂╃暐鍥剧墖
function hook_imagecachePresets()
{
  return array(
    '60x60' => array(
       'type' => 'scale',
       'width' => 60,
       'height' => 60,
    ),
    '67x67' => array(
       'type' => 'scale',
       'width' => 67,
       'height' => 67,
    ),
    '85x85' => array(
        'type' => 'scale',
        'width' => 85,
        'height' => 85,
    ),
    '380X380' => array(
        'type' => 'scaleFillBlankY',
        'width' => 380,
        'height' => 380,
        'quality' => 100,
    ),
  	'300x500' => array(
  		'type' => 'scale',
  		'width' => 300,
  		'height' => 500,
  		'quality' => 100,
  	),
    '345x335' => array(
       'type' => 'scale',
       'width' => 345,
       'height' => 335,
    ),
    '128x192' => array(
       'type' => 'scale',
       'width' => 128,
       'height' => 192,
    ),
    '160x240' => array(
       'type' => 'scale',
       'width' => 160,
       'height' => 240,
    ),
    '210x320' => array(
        'type' => 'scale',
        'width' => 210,
        'height' => 320,
        'quality' => 85,
    ),
    '500x750' => array(
    	'type' => 'scale',
    	'width' => 500,
    	'height' => 750,
    ),
    '187x276' => array(
       'type' => 'scale',
       'width' => 187,
       'height' => 276,
    ),
    '718x317' => array(
       'type' => 'scale',
       'width' => 718,
       'height' => 317,
    ),
    '116x174' => array(
       'type' => 'scale',
       'width' => 116,
       'height' => 174,
    ),
    '118x178' => array(
    	'type' => 'scale',
    	'width' => 118,
    	'height' => 178,
    ),
  	'water_mark' => array(
    	'type' => 'water_mark',
  		'width' => 0,
  		'height' => 0,
    ),
  	'400x640_water_mark' => array(
  		'type' => 'resize_water_mark',
  		'width' => 400,
  		'height' => 640,
  	),
  	'380x380' => array(
  		'type' => 'fill',
  		'width' => 380,
  		'height' => 380,
  	),
  );
}

//重新设置当前位置
function hook_breadcrumb()
{
	$breadcrumb = getBreadcrumb();
	$output = "";
    $row = array_slice($breadcrumb, -2, 1);
    if(count($row) == 0){
    }
	else{
		$row = $row[0];
    	if(isset($row['title'])){
    		$title = isset($row['html']) && $row['html'] ? $row['title'] : plain($row['title']);
    		if($title == "Home"){
    			$output .= '<div class="go_back">';
    			$output .= '<span class="p-arrow-inner"></span>';
    			$output .= '<a href="' . url("product/topmenu") . '">';
    			$output .= '<p>'.'All Categories'.'</p>';
    			$output .= '</a>';
    			$output .= '</div>';
    		}else{
    			str_replace('&amp;amp;', '&amp;', $title);
    			if (isset($row['path'])) {
    				$output .= '<div class="go_back">';
    				$output .= '<span class="p-arrow-inner"></span>';
	    			$output .= '<a href="' . url($row['path']) . '">';
	    			$output .= '<p>'.$title.'</p>';
	    			$output .= '</a>';
	    			$output .= '</div>';
    			} else {
    				$output .= '<div class="go_back">';
    				$output .= '<span class="p-arrow-inner"></span>';
    				$output .= '<a href="' . url($row['path']) . '">';
    				$output .= '<p>'.$title.'</p>';
    				$output .= '</a>';
    				$output .= '</div>';
    			}
    		}
    	}else{
    		$output .= '<div class="go_back">';
    		$output .= '<span class="p-arrow-inner"></span>';
    		$output .= '<a href="' . url("product/topmenu") . '">';
    		$output .= '<p>'.'All Categories'.'</p>';
    		$output .= '</a>';
    		$output .= '</div>';
    	}
    }
    
    return $output;
}
//重定义category 分页。
function hook_category_pagination($urlPage, $pageCount, $page, $firstPath = null){
  $output = '';
  if ($page > 1){
    $previousUrl = categoryURL(str_replace('%d', ''.($page - 1), $urlPage));
    $output .= '<li class="pageControl previous"><a title="page '.($page - 1).'" href="'
    .url($previousUrl).'"><span>Prev&nbsp;</span></a></li>';
  }

  $output .= '<li class="pageIndex">';

  if($page - 1< 3){
    for($i=1; $i<$page; $i++){
      $pageUrl = categoryURL(str_replace('%d', ''.$i, $urlPage));
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
  }else{
    $firstPageUrl = categoryURL(str_replace('%d', '1', $urlPage));
    $output .= '<a title="page 1" href="'.url($firstPageUrl). '">1</a>';
    $output .= '<span>...</span>';
    for($i=$page-2; $i<$page; $i++){
      $pageUrl = categoryURL(str_replace('%d', ''.$i, $urlPage));
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
  }
  $output .= '<strong>'.$page.'</strong>';
  //echo after page.
  if($pageCount - $page < 3){
    for($i=$page + 1; $i <= $pageCount; $i++){
      $pageUrl = categoryURL(str_replace('%d', ''.$i, $urlPage));
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
  }else{
    for($i=$page + 1; $i<$page + 3; $i++){
      $pageUrl = categoryURL(str_replace('%d', ''.$i, $urlPage));
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
    $output .= '<span>...</span>';
    $lastPageUrl = categoryURL(str_replace('%d', ''.$pageCount, $urlPage));
    $output .= '<a title="page '.$pageCount.'" href="'.url($lastPageUrl). '">'.$pageCount.'</a>';
  }
  if ($page < $pageCount){
    $output .= '</li>';
    $nextUrl = categoryURL(str_replace('%d', ''.($page + 1), $urlPage));
    $output .= '<li class="pageControl next"><a title="page '.($page + 1).'" href="'.url($nextUrl).'"><span>Next&nbsp;</span></a></li>';
  }
  return $output;
}


//重定义category 分页。
function hook_common_pagination($urlPage, $pageCount, $page, $firstPath = null, $isCategory = false){
  $output = '';
  
  if ($page < $pageCount){
    $output .= '</li>';
    $nextUrl = str_replace('%d', ''.($page + 1), $urlPage);
    if($isCategory) $nextUrl = categoryURL($nextUrl);
    $output .= '<li class="pageControl next"><a title="page '.($page + 1).'" href="'.url($nextUrl).'"><span>Next&nbsp;</span></a></li>';
  }
  


  $output .= '<li class="pageIndex">';

  if($page - 1< 3){
    for($i=1; $i<$page; $i++){
      $pageUrl = str_replace('%d', ''.$i, $urlPage);
      if($isCategory) $pageUrl = categoryURL($pageUrl);
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
  }else{
    $firstPageUrl = str_replace('%d', '1', $urlPage);
    if($isCategory) $firstPageUrl = categoryURL($firstPageUrl);
    $output .= '<a title="page 1" href="'.url($firstPageUrl). '">1</a>';
    $output .= '<span>...</span>';
    for($i=$page-2; $i<$page; $i++){
      $pageUrl = str_replace('%d', ''.$i, $urlPage);
      if($isCategory) $pageUrl = categoryURL($pageUrl);
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
  }
  $output .= '<strong>'.$page.'</strong>';
  //echo after page.
  if($pageCount - $page < 3){
    for($i=$page + 1; $i <= $pageCount; $i++){
      $pageUrl = str_replace('%d', ''.$i, $urlPage);
      if($isCategory) $pageUrl = categoryURL($pageUrl);
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
  }else{
    for($i=$page + 1; $i<$page + 3; $i++){
      $pageUrl = str_replace('%d', ''.$i, $urlPage);
      if($isCategory) $pageUrl = categoryURL($pageUrl);
      $output .= '<a title="page '.$i.'" href="'.url($pageUrl). '">'. $i. '</a>';
    }
    $output .= '<span>...</span>';
    $lastPageUrl = str_replace('%d', ''.$pageCount, $urlPage);
    if($isCategory) $lastPageUrl = categoryURL($lastPageUrl);
    $output .= '<a title="page '.$pageCount.'" href="'.url($lastPageUrl). '">'.$pageCount.'</a>';
  }

  if ($page > 1){
    $previousUrl = str_replace('%d', ''.($page - 1), $urlPage);
    if($isCategory) $previousUrl = categoryURL($previousUrl);
    $output .= '<li class="pageControl previous"><a title="page '.($page - 1).'" href="'
    .url($previousUrl).'"><span>Prev&nbsp;</span></a></li>';
  }
  
  return $output;
}

 //重定义分页
function hook_pagination($path, $count, $each, $page, $firstPath = null)
{
$pages = ceil($count / $each);
  if (1 >= $pages) {
    return '';
  }
  $output = '';

      $output .= '<a class="independent" href="' . url(isset($firstPath) ? $firstPath : strtr($path, array('%d' => 1))) . '">&lt;Home</a>';

  if ($pages > 1) {
    if ($page == 1) {
      $output .= '<a class="independent">Previous</a>';
    } else {
      $output .= '<a class="independent" href="' . url($page == 2 && isset($firstPath) ? $firstPath : strtr($path, array('%d' => $page - 1))) . '">Previous</a>';
    }
  }
  $from = $page - 5;
  $end = $page + 5;
  if ($from < 1) {
    $end = min($end - $from + 1, $pages);
    $from = 1;
  }
  if ($end > $pages) {
    $from = max($from - $end + $pages, 1);
    $end = $pages;
  }
  for ($i = $from; $i <= $end; ++ $i) {
    if ($page == $i) {
      $output .=  '<a class="independent">'.$i.'</a>';
    } else {
      $output .= '<a class="independent" href="' . url($i == 1 && isset($firstPath) ? $firstPath : strtr($path, array('%d' => $i))) . '">' . $i . '</a>';
    }
  }
  if ($pages > 1) {
    if ($page == $pages) {
      $output .= '<a class="independent">Next</a>';
    } else {
      $output .= '<a  class="independent" href="' . url(strtr($path, array('%d' => $page + 1))) . '">Next</a>';
    }
  }

      $output .= '<a class="independent" href="' . url(strtr($path, array('%d' => $pages))) . '">Last&gt;</a>';
      $output .= '<a class="independent" href="' . url(strtr($path, array('%d' => $pages))) . '">  '.$pages.' pages </a>';
    return $output;
}


//  重定义combo翻页
function hook_combo_pagination($urlPage, $pageCount, $page){
  $output = '';

  if($pageCount == 0){
  	$output = '';
//   	$output = "<p>No data was found</p>";
//   	$output = "<br /><br /><p style='text-align: center;color:red; margin-top:5px'>No data was found</p><br /><br />";
  	return $output;
  }
  if($pageCount == 1){
  	return $output;
  }

  if($page == 1){
	$nexturl = str_replace('%d', ''.($page + 1), $urlPage);
	$nexturl =  $nexturl;
	$nexturl = '<a id="combonext" class="next1 next2" href="' . $nexturl . '">Next</a>' ;
	$preurl = '<a id="combopre" class="pre" style="border: 0.1em #d6d6d6 solid; color: #d6d6d6;">Previous</a>' ;
  }
  else if($page == $pageCount){
	$nexturl = '<a id="combonext" class="next1" style="border: 0.1em #d6d6d6 solid; color: #d6d6d6;">Next</a>' ;
	$preurl = str_replace('%d', ''.($page - 1), $urlPage);
	$preurl =  $preurl;
	$preurl = '<a id="combopre" class="pre pre1" href="' . $preurl . '">Previous</a>' ;
  }
  else{
	$nexturl = str_replace('%d', ''.($page + 1), $urlPage);
	$nexturl = $nexturl;
	$nexturl = '<a id="combonext" class="next1 next2" href="' . $nexturl . '">Next</a>' ;
	$preurl = str_replace('%d', ''.($page - 1), $urlPage);
	$preurl =  $preurl;
	$preurl = '<a id="combopre" class="pre pre1" href="' . $preurl . '">Previous</a>' ;
  }
  
  $pageOptions = array();
  
  for($i=1; $i<=$pageCount; $i++){
	if($i == $page){
		$pageOptions[] = '<option value="' . $i . '" selected>' . 'Page ' . $i . ' of ' . $pageCount . '</option>';
	}else{
		$pageOptions[] = '<option value="' . $i . '">' . 'Page ' . $i . ' of ' . $pageCount . '</option>';
	}
  }
  
  $output .= $preurl;
  $output .= '<select name="page" id="pageselect" class="page">';
  foreach($pageOptions as $option){
	$output .= $option.'<\n>';
  }
  $output .= '</select>';
  $output .= $nexturl;
  
  return $output;
}



