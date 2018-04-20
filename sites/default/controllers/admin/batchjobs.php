<?php
class Admin_Batchjobs_Controller extends Bl_Controller
{
  public function __permissions()
  {
    return array(
      'import product',
      'export product',
      'batch edit product',
      'batch edit pagevariable',
    );
  }


  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
  }


  public function postAction()
  {
    if ($this->isPost()) {
      if (isset($_POST['downfileform'])) {
        $type = $_POST['type'];
        if (!access('export product')) {
           goto403('Access Denied.');
        }
        $this->_batchExportProductTemplate($type);
      } else if (isset($_POST['uploadfile'])){
        if (!access('import product')) {
          goto403('Access Denied.');
        }
        $this->_batchLeadProduct('filedata');
      } else {
        if (!access('export product')) {
          goto403('Access Denied.');
        }
        $this->_batchExportProduct($_POST);
      }
    } else {
      gotoUrl('admin');
    }
  }


  /**
   * 批量导入商品
   */
  private function _batchLeadProduct($filedata)
  {
    if(isset($_FILES['filedata'])){
      $fileModel = File_Model::getInstance();
      $file = $fileModel->uploadFile($filedata, array('type'=>'csv'));
      $productModel = Product_Model::getInstance();
      $taxonomyModel = Taxonomy_Model::getInstance();
      if (isset($file->filepath)) {
        $filename = 'images/'.$file->filepath;
        if (file_exists($filename) && filesize($filename)) {
          require LIBPATH . '/PHPExcel.php';
          require LIBPATH . '/PHPExcel/Writer/Excel5.php';
          $objReader = new PHPExcel_Reader_Excel5();
          $objPHPExcel = $objReader->load($filename);
          $objWorksheet = $objPHPExcel->getActiveSheet();
          $i = 0;
          foreach ($objWorksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
              $array[$i][] = $cell->getValue();
            }
            ++$i;
          }
          foreach($array[0] as $key => $dl){
            $filed_arr = explode(':', $dl);
            if ($key == 0){
              $type = $filed_arr[0];
            } else {
              if (isset($filed_arr[2]) && $filed_arr[2] == 1) {
                if ($dl) {
                  $fileds['type'][$key] = $filed_arr[0];
                }
              } else {
                if ($dl) {
                  $fileds['product'][$key] = $filed_arr[0];
                }
              }
            }
          }
          unset($array[0]);
          $productFields = $fileds['product'];
          $typeFields = isset($fileds['type']) ? $fileds['type'] : null;
          foreach ($array as $key => $dl) {
            $set_product[$key]['type'] = $type;
            foreach ($productFields as $key2 => $dll) {
              if($dll == 'directory' || $dll == 'brand'){
                $dlarr = $taxonomyModel->getTermInfoByName($dl[$key2]);
                $dl[$key2] = $dlarr->tid;
                $set_product[$key][$dll.'_tid'] = $dl[$key2];
              } else {
                $set_product[$key][$dll] = $dl[$key2];
              }
            }
            $path_alias = $this->getAliasRealize($set_product[$key]['name']);
            $set_product[$key]['path_alias'] = $this->_getpathAlias($path_alias);
            if (!(boolean)$productModel->getProductInfoByPathAlias($path_alias)) {
              $set_product[$key]['path_alias'] = $path_alias;
            }
            $set_product[$key]['created'] = TIMESTAMP;
            $set_product[$key]['updated'] = TIMESTAMP;
            if (isset($typeFields)) {
              foreach ($typeFields as $key2 => $dll) {
                $set_type[$key]['field_'.$dll] = $dl[$key2];
              }
            }
            isset($set_type) ? $set_type : $set_type = null;
          }
          $productModel->batchLeadProduct($type, $set_product, $set_type, $array);
          setMessage('成功 '.count($set_product).' 个商品', 'notice');
        } else {
          setMessage('没有找到上传的文件', 'error');
        }
        gotourl('admin/product/list');
      }
    }
  }

  private function _getpathAlias($path_alias)
  {
    $productModel = Product_Model::getInstance();
    if ((boolean)$productModel->getProductInfoByPathAlias($path_alias)) {
      return $this->_getpathAlias($path_alias.'-a');
    } else {
      return $path_alias;
    }
  }

  /**
   * 导出商品模板
   */
  private function _batchExportProductTemplate($type)
  {
    if (!empty($type)) {
      require LIBPATH . '/PHPExcel.php';
      $objPHPExcel = new PHPExcel();
      set_time_limit(2000);
      $productModel = Product_Model::getInstance();
      $productFileds = $productModel->getProductFieldsName();
      $arr = $productModel->getTypeFieldsList($type);
      $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, 1, $type.':产品类型');
      $i = 1;
      foreach ($productFileds as $key => $dl) {
        if (!in_array($dl, array('path_alias', 'created', 'updated', 'pvid', 'visible', 'weight')) ) {
          $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, 1, $dl);
          $i++;
        }
      }

      foreach($arr as $key => $dl){
        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, 1, $key.':'.$dl->name.':1');
        $i++;
      }
      $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
      header('Content-Type: application/vnd.ms-excel; charset=utf-8');
      header("Content-Disposition: attachment;filename=product_".$type.".xls");
      header('Cache-Control: max-age=0');
      $objWriter->save('php://output');
    } else {
      setMessage('导出商品模板错误', 'error');
      gotourl('admin/batchjobs/showBatchExportProduct');
    }

  }

  /**
   * 批量导出商品template
   */
  public function showBatchExportProductAction()
  {
    if (!access('export product')) {
      goto403('Access Denied.');
    }
    $productModel = Product_Model::getInstance();
    $typeList = $productModel->getTypeList();
    $this->view->render('admin/product/batchexport.phtml', array(
      'typeList' => $typeList,
    ));
  }

  private function _batchExportProduct($post)
  {
    if (isset($post['checkbox'])) {
      $pids = $post['checkbox'];
      require LIBPATH . '/PHPExcel.php';
      $objPHPExcel = new PHPExcel();
      set_time_limit(2000);
      $productModel = Product_Model::getInstance();
      $taxonomyModel = Taxonomy_Model::getInstance();
      $j = 2;
      $k = 0;
      foreach ($pids as $key => $pid) {
        $product_info = $productModel->getProductInfo($pid);
        if ($product_info->directory_tid) {
          $termsInfo = $taxonomyModel->getTermInfo($product_info->directory_tid);
          $product_info->directory = $termsInfo->name;
          unset($product_info->directory_tid);
        }
        if ($product_info->brand_tid) {
          $termsInfo = $taxonomyModel->getTermInfo($product_info->brand_tid);
          $product_info->brand = $termsInfo->name;;
          unset($product_info->brand_tid);
        }
        $i = 0;
        foreach ($product_info as $key2 => $dl) {
          $dl = !empty($dl) ? $dl : 0;
          if ($dl) {
            if ($k == 0) {
              $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, 1, $key2);
            }
            if ($key2 == 'directory_tid' || $key2 == 'brand_tid'){
              $taxonomyModel = Taxonomy_Model::getInstance();
              $dlarr = $taxonomyModel->getTermInfo($dl);
              $dl = $dlarr->name;
            }
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($i, $j, $dl);
            ++$i;
          }
        }
        ++$k;
        ++$j;
      }
      $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
      header('Content-Type: application/vnd.ms-excel; charset=utf-8');
      header("Content-Disposition: attachment;filename=book_".time().".xls");
      header('Cache-Control: max-age=0');
      $objWriter->save('php://output');
    } else {
      setMessage('导出商品错误', 'error');
    }
    gotourl('admin/product/list');
  }

  /**
   * 获取中文翻译
   * @param string $str 待翻译词
   * @return string 翻译后的英文
   */
  public function getAliasRealize($str)
  {
    $commonInstance = Common_Model::getInstance();
    $alias = $commonInstance->callFunction('translate', urldecode($str));
    return $alias;
  }


  /**
   * 批量修改商品, added by 55feng (2010-10-14)
   *
   *
   */
  public function batchEditProductsAction()
  {
    if (!access('batch edit product')) {
      goto403('Access Denied.');
    }

    if ($this->isPost()) {
			set_time_limit(0);

      $commonInstance = Common_Model::getInstance();
      $taxonomyInstance = Taxonomy_Model::getInstance();
      $pagevariableInstance = PageVariable_Model::getInstance();
      $productInstance = Product_Model::getInstance();
      $pidList = json_decode($_POST['BatchPidList']);
      $post = (object)$_POST;
      $post->brand_tid = isset($post->brand) ? $post->brand : 0;
      $productInfo = $post;

      $url = 'admin/batchjobs/batchEditProducts';
      if (!is_array($pidList) || count($pidList) < 1) {
      	setMessage('请指定您要修改的商品', 'error');
      	gotoBack($url);
      }
      
      if (isset($productInfo->changefields) && !isset($productInfo->changeType)) {
        setMessage('修改商品属性必须选择修改商品类型', 'error');
        gotoBack($url);
      }

      if (!isset($productInfo->sell_price) || !is_numeric($productInfo->sell_price)) {
        setMessage('商品销售价格必须为数字', 'error');
        gotoBack($url);
      }

      if ($productInfo->directory_tid4) {
        $productInfo->directory_tid = $productInfo->directory_tid4;
      } elseif ($productInfo->directory_tid3) {
      	$productInfo->directory_tid = $productInfo->directory_tid3;
      } else if ($productInfo->directory_tid2) {
      	$productInfo->directory_tid = $productInfo->directory_tid2;
      } else if ($productInfo->directory_tid1) {
      	$productInfo->directory_tid = $productInfo->directory_tid1;
      } else {
      	$productInfo->directory_tid = 0;
      }

      $set = array(
          'status' => isset($productInfo->status) ? 1 : 0,                 //上架
          'shippable' => isset($productInfo->shippable) ? 1 : 0,           //可配送
          'free_shipping' => isset($productInfo->free_shipping) ? 1 : 0,   //免运费
          'directory_tid' => $productInfo->directory_tid,                  //商品的目录
          'brand_tid' => $productInfo->brand_tid,                          //品牌
          'sell_price' => $productInfo->sell_price,                        //销售价
          'list_price' => $productInfo->list_price,                        //市场价
          'wt' => $productInfo->wt,                                        //重量
          'stock' => intval($productInfo->stock),                          //库存
          'sell_min' => isset($productInfo->sell_min) ? intval($productInfo->sell_min) : 0,
          'sell_max' => isset($productInfo->sell_max) ? intval($productInfo->sell_max) : 0,
          'summary' => trim($productInfo->summary),
          'description' => trim($productInfo->description),
          'template' => trim($productInfo->template),
          'type' => trim($productInfo->type),
      );

      $isChange = array(
          'changeDirectory' => 'directory_tid',
          'changeBrand' => 'brand_tid',
          'changeSell_price' => 'sell_price',
          'changeList_price' => 'list_price',
          'changeWt' => 'wt',
          'changeStock' => 'stock',
          'changeSell_min' => 'sell_min',
          'changeSell_max' => 'sell_max',
          'changeSummary' => 'summary',
          'changeDescription' => 'description',
          'changeTemplate' => 'template',
          'changeStatus' => 'status',
          'changeShippable' => 'shippable',
          'changeFree_shipping' => 'free_shipping',
          'changeType' => 'type',
      );

      $isChangeReplace = array(
          'changeProductURL' => 'ProductURL',
          'changePVTitle' => 'PVTitle',
          'changePVKeywords' => 'PVKeywords',
          'changePVDescription' => 'PVDescription',
          'changePVVar1' => 'PVVar1',
          'changePVVar2' => 'PVVar2',
          'changePVVar3' => 'PVVar3',
          'changePVVar4' => 'PVVar4',
          'changePVVar5' => 'PVVar5',
          'changePVVar6' => 'PVVar6',
      );
      $setReplace = array();
      //按规则替换
      foreach ($isChangeReplace as $key => $value) {
      	if(isset($productInfo->$key)){
      		$setReplace[$value] = $productInfo->$value;
      	}
      }

      $newSet = array();
      foreach($isChange as $key => $value){
      	if(isset($productInfo->$key)){
      		$newSet[$value] = $set[$value];
      	}
      }

      $pidList = array_unique($pidList);

      //循环修改选定的商品
      foreach($pidList as $key => $pid){

      	//获得按百分比修改的销售价和市场价值
      	if (isset($productInfo->changeSell_pricePercent) || isset($productInfo->changeList_pricePercent)) {
      		$productOldInfo = $productInstance->getProductInfo($pid);
      		$sellPriceOld = $productOldInfo->sell_price;
      		$sellPercent = intval($productInfo->sell_pricePercent)/100;
      		$listPriceOld = $productOldInfo->list_price;
      		$listPercent = intval($productInfo->list_pricePercent)/100;

      		if (isset($productInfo->changeSell_pricePercent)) $newSet['sell_price'] = $sellPriceOld * $sellPercent;
      		if (isset($productInfo->changeList_pricePercent)) $newSet['list_price'] = $listPriceOld * $listPercent;
      	}

      	if (isset($productInfo->sphinx_key) && isset($productInfo->changeSphinxKey)) {
      		$productInstance->concatProductSearchKey($pid, $productInfo->sphinx_key);
      	}

      	//先修改一些指定修改的值
      	if(count($newSet)>0){
      		$productInstance->updateProduct($pid, $newSet);
      	}
      	//修改所属分类
      	if(!isset($post->terms_products)){
      		$post->terms_products = array();
      	}
      	if(isset($productInfo->changeTerms_products)){
      		$productInstance->updateProductTerms($pid, $post->terms_products);
      	}

      	//保存会员价格
      	if ($pid && isset($post->changeSell_price) && isset($_POST['rank_price_check'])
      	&& !empty($_POST['rank_price_check'])) {
      		$productInstance->deleteProductRanksPrice($pid);
      		$set = array();
      		foreach ($_POST['rank_price'] as $rid => $price) {
      			if (!isset($_POST['rank_price_check'][$rid]) || !is_numeric(trim($price))) continue;
      			$set[$rid] = trim($price);
      		}
      		$productInstance->insertProductRanksPrice($pid, $set);
      	}

      	//按比例修改会员价格
      	if ($pid && isset($post->changeSell_pricePercent)
      	&& isset($_POST['rank_pricePercent_check']) && !empty($_POST['rank_pricePercent_check'])) {
      		$set = array();
      		foreach ($_POST['rank_pricePercent'] as $rid => $pricePercent) {
      			if (!isset($_POST['rank_pricePercent_check'][$rid]) || !is_numeric(trim($pricePercent))) continue;
      			if (!$oldRandPrice = $productInstance->getProductRanksPriceByRID($pid, $rid)) continue;
      			$percent = intval($pricePercent)/100;
      			$newRandPrice = $oldRandPrice * $percent;
      			$productInstance->updateProductRandPriceByRID($pid, $rid, $newRandPrice);
      		}
      	}
      	
      	//修改所属类别
      	if (isset($productInfo->changefields)) {
      	  $productInstance->updateProductFields($pid, $productInfo->type, $productInfo);
      	}
      	$this->replaceUpdateProduct($pid, $setReplace, $post, $commonInstance, $productInstance, $taxonomyInstance, $pagevariableInstance);
      }// end foreach;
      setMessage('修改商品成功');
      gotoBack($url);
    }// end if (_POST)

    $vocabularyList = array();  // 商品分类
    $termsList = array();       // 所有分类词列表
    $productTerms = array();    // 商品所属分类词

    $taxonomyInstance = Taxonomy_Model::getInstance();
    $brandTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_BRAND, false);
    $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
    $userInstance = User_Model::getInstance();
    $ranks = $userInstance->getRanksList();
    $productInstance = Product_Model::getInstance();
    $typeList = $productInstance->getTypeList();
    $vocabularyList = $taxonomyInstance->getVocabularyList();
    foreach ($vocabularyList AS $vid => $vocabulary) {
      if ($vid == Taxonomy_Model::TYPE_BRAND || $vid == Taxonomy_Model::TYPE_DIRECTORY) {
        continue;
      }
      $termsList[$vid] = $taxonomyInstance->getTermsList($vid, false);
    }
    $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
    $this->view->render('admin/product/batchedit.phtml', array(
      'brandList' => $brandTermsList,
      'typeList' => $typeList,
      'directoryList' => $directoryList,
      'ranks' => $ranks,
      'vocabularyList' => $vocabularyList,
      'termsList' => $termsList,
    ));
  }

  function replaceUpdateProduct($pid, $setReplace, $post, Common_Model $ci, Product_Model $pi, Taxonomy_Model $ti, PageVariable_Model $pvi)
  {
    $keyField = array(
          'ProductURL' => 'path_alias',
          'PVTitle' => 'title',
          'PVKeywords' => 'meta_keywords',
          'PVDescription' => 'meta_description',
          'PVVar1' => 'var1',
          'PVVar2' => 'var2',
          'PVVar3' => 'var3',
          'PVVar4' => 'var4',
          'PVVar5' => 'var5',
          'PVVar6' => 'var6',
    );

    if (!is_array($setReplace) || count($setReplace) < 1) {
      return false;
    }

    /*
     * 查出页面的这些信息后替换更新：
     *
     {products.name}为商品名称，{products.id}为商品id
     {products.price}为商品价格，{directory1}为一级商品目录名称
     {directory2}为二级商品目录名称，{directory3}为三级商品目录名称
     {brand}为品牌名
     */
    $productInstance = $pi;//Product_Model::getInstance();
    $taxonomyInstance = $ti;//Taxonomy_Model::getInstance();

    $productInfo = $productInstance->getProductInfo($pid);
    $directoryInfo = $taxonomyInstance->getTermParents($productInfo->directory_tid);

    //获取目录名称
    $directory = array();
    if (!isset($directoryInfo)) {
      $directory[0] = $productInfo->directory_tid;
      $directory[1] = null;
      $directory[2] = null;
    } else if (isset($directoryInfo[0]) && !isset($directoryInfo[1])) {
      $directory[0] = $directoryInfo[0];
      $directory[1] = $productInfo->directory_tid;
      $directory[2] = null;
    } else if (isset($directoryInfo[0]) && isset($directoryInfo[1])) {
      $directory[0] = $directoryInfo[1];
      $directory[1] = $directoryInfo[0];
      $directory[2] = $productInfo->directory_tid;
    }

    $directoryList = array();
    if (isset($directory) && count($directory) > 0)
    foreach ($directory as $key => $tid) {
      if (!isset($tid)) continue;
      $termInfo= $taxonomyInstance->getTermInfo($tid);
      if ($termInfo) $directoryList[$key] = $termInfo->name;
    }
    $directory = $directoryList;
    unset($directoryList);
    unset($directoryInfo);

    //获取品牌名称
    $brand = $taxonomyInstance->getTermInfo($productInfo->brand_tid);
    $replacedValue = array();
    foreach ($setReplace as $postName => $fieldValue) {
      $fieldValue = str_replace('{products.name}', $productInfo->name, $fieldValue);
      $fieldValue = str_replace('{products.id}', $productInfo->pid, $fieldValue);
      $fieldValue = str_replace('{products.price}', $productInfo->sell_price  , $fieldValue);
      $fieldValue = str_replace('{directory1}', isset($directory[0]) ? $directory[0] : '{{{}}}', $fieldValue);
      $fieldValue = str_replace('{directory2}', isset($directory[1]) ? $directory[1] : '{{{}}}', $fieldValue);
      $fieldValue = str_replace('{directory3}', isset($directory[2]) ? $directory[2] : '{{{}}}', $fieldValue);
      $fieldValue = str_replace('{brand}', $brand ? $brand->name : '', $fieldValue);
      $fieldValue = str_replace('-{{{', '', $fieldValue);
      $fieldValue = str_replace('{{{', '', $fieldValue);
      $fieldValue = str_replace('}}}-', '', $fieldValue);
      $fieldValue = str_replace('}}}', '', $fieldValue);
      $fieldName = $keyField[$postName];
      $replacedValue[$fieldName] = $fieldValue;
    }

    unset($directory);
    unset($directoryList);
    unset($brand);

    //更新商品页面信息
    $pageVariableSet = array(
        'title' => isset($replacedValue['title']) ? $replacedValue['title'] : null,
        'meta_keywords' => isset($replacedValue['meta_keywords']) ? $replacedValue['meta_keywords'] : null,
        'meta_description' => isset($replacedValue['meta_description']) ? $replacedValue['meta_description'] : null,
        'var1' => isset($replacedValue['var1']) ? $replacedValue['var1'] : null,
        'var2' => isset($replacedValue['var2']) ? $replacedValue['var2'] : null,
        'var3' => isset($replacedValue['var3']) ? $replacedValue['var3'] : null,
        'var4' => isset($replacedValue['var4']) ? $replacedValue['var4'] : null,
        'var5' => isset($replacedValue['var5']) ? $replacedValue['var5'] : null,
        'var6' => isset($replacedValue['var6']) ? $replacedValue['var6'] : null,
    );
    $newPageVariableSet = array();
    foreach ($pageVariableSet as $key => $value) {
      if (!isset($value)) continue;
      $newPageVariableSet[$key] = $value;
    }

    $productSet = array();
    if (count($newPageVariableSet) > 0) {
      $pagevariableInstance = $pvi;
      if ($productInfo->pvid != 0){
        $productSet['pvid'] = $pagevariableInstance->updatePageVariables($productInfo->pvid, $newPageVariableSet);
      } else if ($productInfo->pvid == 0) {
        $productSet['pvid'] = $pagevariableInstance->insertPageVariables($newPageVariableSet);
      }
    }

    //更新商品ULR
    if (isset($replacedValue['path_alias']) || isset($productSet['pvid'])) {
      if (isset($replacedValue['path_alias'])) {
        $pathAlias = $ci->translate($replacedValue['path_alias']);
        // 检查重复的路径别名, 自动加数字后缀
        $pathAliases = $productInstance->getProductPathAliasList($pathAlias);
        if (!empty($pathAliases) && ($pid != array_search($pathAlias, $pathAliases))) {
          $n = 1;
          while(array_search($pathAlias . '-' . $n, $pathAliases)) {
            ++$n;
          }
          $pathAlias .= '-' . $n;
        }
        if ($replacedValue['path_alias'] == '') $productSet['path_alias'] = null;
        else $productSet['path_alias'] = $pathAlias;

      }
      $productInstance->updateProduct($pid, $productSet);
    }

  }

  /**
   * 根据分类词id, tid来获取商品列表, 以json数组的方式输出, added by 55feng (2010-10-14)
   * @param $tid, 分类词id
   */
  public function productsJsonListAction()
  {
	  $taxonomyInstance = Taxonomy_Model::getInstance();
	  $productInstance = Product_Model::getInstance();

  	$filter = array();
  	$filter['name'] = isset($_GET['name']) ? $_GET['name'] : null;
  	$filter['sn'] = isset($_GET['sn']) ? $_GET['sn'] : null;
  	$filter['pid'] = isset($_GET['pid']) ? $_GET['pid'] : null;
  	$filter['number'] = isset($_GET['number']) ? $_GET['number'] : null;
  	$filter['brand_tid'] = isset($_GET['brand_tid']) ? $_GET['brand_tid'] : null;
  	$filter['status'] = isset($_GET['status']) ? $_GET['status'] : null;
  	$filter['sell_price_low'] = isset($_GET['lowPrice']) ? $_GET['lowPrice'] : null;
  	$filter['sell_price_heigh'] = isset($_GET['highPrice']) ? $_GET['highPrice'] : null;
  	$filter['directory_tid'] = isset($_GET['tid']) ? $_GET['tid'] : null;

    $productList = array();
    $productList = $productInstance->getBatchProductsNameList($filter);
    exit(json_encode($productList));
  }

  /**
   * 批量删除商品
   */
  function batchDeleteProductsAction()
  {
    global $user;
    if (!access('delete product')) {
      goto403('Access Denied.');
    }

    $productInstance = Product_Model::getInstance();
    $productsList = json_decode( $_POST['pidlist'] );
    $all = 0;
    $error = 0;
    $correct = 0;
    if(is_array($productsList)&&count($productsList)>0){
      foreach($productsList as $key=>$pid){
        if (!$productInfo = $productInstance->getProductInfo($pid)) {
          $error++;
        } else {
          $productInstance->deleteProduct($pid);
          $correct++;
        }
        $all++;
      }
    }
    if($error==0){
      exit('OK');
    }else{
      exit($correct.'/'.$all);
    }
  }

  /**
   * 批量修改分类页面信息
   */
  public function batchEditTermsPageVariableAction()
  {

  }

}
