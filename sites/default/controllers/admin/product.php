<?php
class Admin_Product_Controller extends Bl_Controller
{
    private $_productInstance;
    private $_taxonomyModel;

    public static function __permissions()
    {
        return array(
      'list product',
      'edit product',
      'copy product',
      'batch copy product',
      'batch add product tag',
      'delete product',
      'delete product image cache',
      'batch uplode',
      'list promotions',
      'list type',
      'edit type',
      'delete type',
        );
    }

    public function init()
    {
        $uri = Bl_Core::getRouter();
        if (!access('administrator page') && $uri['action'] != 'uploadProduct') {
            goto403('<a href="' . url('admin/login') . '">登录</a>');
        }
        $this->_productInstance = Product_Model::getInstance();
        $this->_taxonomyModel = Taxonomy_Model::getInstance();
    }

    public function indexAction()
    {
        $this->listAction();
    }

    public function typelistAction()
    {
        if (!access('list type')) {
            goto403('Access Denied.');
        }
        $typeList = $this->_productInstance->getTypeList();
        $this->view->assign('typeList', $typeList);
        $this->view->render('admin/product/typelist.phtml');
    }
     
    public function filterfieldlistAction($type = null)
    {
        if (!access('edit type')) {
            goto403('Access Denied.');
        }
        if (isset($type) && !$typeInfo = $this->_productInstance->getTypeInfo($type)) {
            setMessage('Product type <em>' . $type . '</em> not found.');
            gotoUrl('admin/product/typelist');
        }
        $typeInfo->filter_fields = TypeFilterField_Model::getInstance()->getTypeFilterFieldByType($type);
        $this->view->render('admin/product/filterfieldlist.phtml', array(
                            'type' => $typeInfo
        ));
    }
    public function typeeditAction($type = null)
    {
        if (!access('edit type')) {
            goto403('Access Denied.');
        }
        if (isset($type) && !$typeInfo = $this->_productInstance->getTypeInfo($type)) {
            setMessage('Product type <em>' . $type . '</em> not found.');
            gotoUrl('admin/product/typelist');
        }
        if ($this->isPost()) {
            $post = (object)$_POST;
            if (isset($type)) {
                $post->type = $typeInfo->type;
            }
            $typeInfo = $post;
            if (!isset($typeInfo->type) || !$this->_productInstance->checkTypeIsValid(trim($typeInfo->type))) {
                setMessage('类型标识错误', 'error');
            } else if (!isset($typeInfo->name) || trim($typeInfo->name) == '') {
                setMessage('类型名称错误', 'error');
            } else {
                $set = array(
          'type' => trim($typeInfo->type),
          'name' => trim($typeInfo->name),
          'name_cn' => trim($typeInfo->name_cn),
                );
                if (isset($type)) {
                    $this->_productInstance->updateType($type, $set);
                    gotoUrl('admin/product/typelist');
                } else {
                    if ($this->_productInstance->getTypeInfo($set['type'])) {
                        setMessage('类型标识已存在', 'error');
                    } else {
                        $this->_productInstance->insertType($set);
                        gotoUrl('admin/product/typelist');
                    }
                }
            }
        } else if (!isset($type)) {
            $typeInfo = null;
        }
        if (isset($type)) {
            $typeInfo->fields = $this->_productInstance->getTypeFieldsList($type);
            $this->view->assign('fieldType', $this->_productInstance->fieldType);
            $this->view->assign('displayType', $this->_productInstance->displayType);
            $typeInfo->productsCount = $this->_productInstance->getProductsCount(array('type' => $type));
            $typeInfo->filter_fields = TypeFilterField_Model::getInstance()->getTypeFilterFieldByType($type);
        }
        $this->view->assign('isnew', !isset($type));
        $this->view->assign('type', $typeInfo);
        $this->view->render('admin/product/typeinfo.phtml');
    }

    public function typedeleteAction($type)
    {
        if (!access('delete type')) {
            goto403('Access Denied.');
        }
        if (!$this->_productInstance->getTypeInfo($type)) {
            setMessage('Product type <em>' . $type . '</em> not found.');
        } else {
            $this->_productInstance->deleteType($type);
        }
        gotoUrl('admin/product/typelist');
    }

    public function fieldeditAction($type, $field = null)
    {
        if (!access('edit type')) {
            goto403('Access Denied.');
        }
        if (!$typeInfo = $this->_productInstance->getTypeInfo($type)) {
            setMessage('Product type <em>' . $type . '</em> not found.');
            gotoUrl('admin/product/typelist');
        }
        if (isset($field) && !$fieldInfo = $this->_productInstance->getTypeFieldInfo($type, $field)) {
            setMessage('Field <em>' . $field . '</em> not found.');
            gotoUrl('admin/product/typeedit/' . $type);
        }
        if ($this->isPost()) {
            $fieldInfo = (object)$_POST;
            if (isset($field)) {
                if (!isset($fieldInfo->name) || trim($fieldInfo->name) == '') {
                    setMessage('属性名称错误', 'error');
                } else {
                    $set = array(
            'name' => trim($fieldInfo->name),
            'required' => isset($fieldInfo->required),
            'indexed' => isset($fieldInfo->indexed),
            'field_size' => isset($fieldInfo->field_size) ? intval($fieldInfo->field_size) : 0,
            'weight' => isset($fieldInfo->weight) ? intval($fieldInfo->weight) : 0,
          	'is_spec' => isset($fieldInfo->is_spec),
                    );
                    if (isset($fieldInfo->display_type)) {
                        $set['display_type'] = intval($fieldInfo->display_type);
                    }
                    $settings = array(
            'options' => '',
            'default_value' => '',
                    );
                    if (isset($fieldInfo->options)) {
                        $settings['options'] = $fieldInfo->options;
                    }
                    if (isset($fieldInfo->default_value)) {
                        $settings['default_value'] = $fieldInfo->default_value;
                    }
                    $set['settings'] = $settings;
                    $this->_productInstance->updateTypeField($type, $field, $set);
                    gotoUrl('admin/product/typeedit/' . $type);
                }
            } else {
                if (!isset($fieldInfo->field_name) || !$this->_productInstance->checkFieldNameIsValid(trim($fieldInfo->field_name))) {
                    setMessage('属性标识错误', 'error');
                } else if (!isset($fieldInfo->name) || trim($fieldInfo->name) == '') {
                    setMessage('属性名称错误', 'error');
                } else if (!isset($fieldInfo->field_type) || !array_key_exists($fieldInfo->field_type, $this->_productInstance->fieldType)) {
                    setMessage('属性类型错误', 'error');
                } else {
                    $fieldName = trim($fieldInfo->field_name);
                    $settings = array(
            'options' => '',
            'default_value' => '',
                    );
                    $set = array(
            'field_name' => $fieldName,
            'name' => trim($fieldInfo->name),
            'field_type' => $fieldInfo->field_type,
            'required' => isset($fieldInfo->required),
            'multiple' => isset($fieldInfo->multiple),
            'indexed' => isset($fieldInfo->indexed),
            'valued' => isset($fieldInfo->valued),
            'settings' => $settings,
            'weight' => 0,
            'is_spec' => isset($fieldInfo->is_spec),
                    );
                    if ($this->_productInstance->getTypeFieldInfo($type, $set['field_name'])) {
                        setMessage('属性标识已存在', 'error');
                    } else {
                        $this->_productInstance->insertTypeField($type, $set);
                        gotoUrl('admin/product/fieldedit/' . $type . '/' . $fieldName);
                    }
                }
            }
        } else {
            if (isset($field)) {
                $displayType = $this->_productInstance->displayType;
                $fieldDisplayType = $this->_productInstance->fieldDisplayType[$fieldInfo->field_type];
                foreach ($displayType as $key => $value) {
                    if (!in_array($key, $fieldDisplayType)) {
                        unset($displayType[$key]);
                    }
                }
                $this->view->assign('displayType', $displayType);
            } else {
                $fieldInfo = null;
            }
        }
        $this->view->assign('isnew', !isset($field));
        $this->view->assign('fieldType', $this->_productInstance->fieldType);
        $this->view->assign('type', $typeInfo);
        $this->view->assign('field', $fieldInfo);
        if (isset($field)) {
            $this->view->render('admin/product/fieldinfo.phtml');
        } else {
            $this->view->render('admin/product/fieldnew.phtml');
        }
    }

    public function fielddeleteAction($type, $field)
    {
        if (!access('edit type')) {
            goto403('Access Denied.');
        }
        if (!$this->_productInstance->getTypeInfo($type)) {
            setMessage('Product type <em>' . $type . '</em> not found.');
            gotoUrl('admin/product/typelist');
        }
        if (!$fieldInfo = $this->_productInstance->getTypeFieldInfo($type, $field)) {
            setMessage('Field <em>' . $field . '</em> not found.');
            gotoUrl('admin/product/typeedit/' . $type);
        }
        $this->_productInstance->deleteTypeField($type, $field);
        gotoUrl('admin/product/typeedit/' . $type);
    }

    public function ajaxGetFilterFieldListAction($type, $productsType)
    {
        $filterFieldList = $this->getFilterFieldList($type, $productsType);
        echo json_encode($filterFieldList);
        exit -1;
    }

    public function getFilterFieldAttrValueList($type, $productType, $attr)
    {
        $attrValueList = array();
        switch ($type)
        {
            case TypeFilterField_Model::ATTR_TYPE_SELF:
                break;
            case TypeFilterField_Model::ATTR_TYPE_MULTI:
                $attrValueList = Product_Model::getInstance()->getAttrValuesList($productType, $attr);
                break;
            case TypeFilterField_Model::ATTR_TYPE_TAG:
                // 1 stands for tag
                $termList = Taxonomy_Model::getInstance()->getTermsList(1, false);
                foreach ($termList as $term)
                {
                    $attrValueList[] = $term->name;
                }
                break;
            case TypeFilterField_Model::ATTR_TYPE_RECOMMENED:
                // 4 stands for recommended
                $termList = Taxonomy_Model::getInstance()->getTermsList(4, false);
                foreach ($termList as $term)
                {
                    $attrValueList[] = $term->name;
                }
                break;
            case TypeFilterField_Model::ATRR_TYPE_BRAND:
                // 2 stands for brand
                $termList = Taxonomy_Model::getInstance()->getTermsList(2, false);
                foreach ($termList as $term)
                {
                    $attrValueList[] = $term->name;
                }
                break;
            default:
                break;
        }
        return $attrValueList;
    }
    public function ajaxGetFilterFieldAttrValueListAction($type, $productType, $attr)
    {
        $attrValueList = $this->getFilterFieldAttrValueList($type, $productType, $attr);
        echo json_encode($attrValueList);
        exit -1;
    }
    
    public function getFilterFieldList($type,$productsType)
    {
        $filterFieldList = array();
        switch ($type)
        {
            case TypeFilterField_Model::ATTR_TYPE_SELF:
                $filterFieldList = ProductFilterAttr_Model::getInstance()->getProductFilterAttrList();
                break;
            case TypeFilterField_Model::ATTR_TYPE_MULTI:
                $typeFieldList = Product_Model::getInstance()->getTypeFieldsList($productsType);
                foreach ($typeFieldList as $typeField)
                {
                    //if ($typeField->multiple)
                    //{
                        $filterField = new stdClass();
                        $filterField->field_name = $typeField->field_name;
                        array_push($filterFieldList, $filterField);
                    //}
                }
                break;
            case TypeFilterField_Model::ATTR_TYPE_TAG:
                $filterField = new stdClass();
                $filterField->field_name = "tag";
                array_push($filterFieldList, $filterField);
                break;
            case TypeFilterField_Model::ATTR_TYPE_RECOMMENED:
                $filterField = new stdClass();
                $filterField->field_name = "recommend";
                array_push($filterFieldList, $filterField);
                break;
            case TypeFilterField_Model::ATRR_TYPE_BRAND:
                $filterField = new stdClass();
                $filterField->field_name = "brand";
                array_push($filterFieldList, $filterField);
                break;
            default:
                break;
        }
        return $filterFieldList;
    }

    public function checkFormPara($post, &$typeFilterField)
    {
        $len = strlen(trim($post['name']));
        if ($len == 0)
        {
            setMessage('选项名不能为空', "error");
            return false;
        }
        else if ($len > 64)
        {
            setMessage('选项名不能超过64个字符', "error");
            return false;
        }
        if ($post['filter_field_value_type'] == "1")
        {
            $minValue = trim($post['min_value']);
            if (strlen($minValue) == 0)
            {
                setMessage('最小值不能为空', "error");
                return false;
            }
            if (!preg_match('/^[-]?[0-9]+.?[0-9]*$/', $minValue))
            {
                setMessage('最小值包含非法字符，请使用数字', "error");
                return false;
            }
            $maxValue = trim($post['max_value']);
            if (strlen($maxValue) == 0)
            {
                setMessage('最大值不能为空', "error");
                return false;
            }
            if (!preg_match('/^[-]?[0-9]+.?[0-9]*$/', $maxValue))
            {
                setMessage('最大值包含非法字符，请使用数字', "error");
                return false;
            }
        }
        else
        {
            $len = strlen(trim($post['menu_values']));
            if ($len == 0)
            {
                setMessage('选项值不能为空', "error");
                return false;
            }
        }
        $typeFilterField = new stdClass();
        if (isset($post['filter_field_id']))
        {
            $typeFilterField->id = intval($post['filter_field_id']);
        }
        $typeFilterField->name = trim($post['name']);
        $typeFilterField->product_type = $post['product_type'];
        $typeFilterField->attr_name = $post['filter_field_attr_name'];
        $typeFilterField->attr_type = intval($post['filter_field_attr_type']);
        $typeFilterField->value_type = intval($post['filter_field_value_type']);
        if ($typeFilterField->value_type == TypeFilterField_Model::ATTR_VALUE_TYPE_SCALAR)
        {
            $typeFilterField->values = array('min_value' => doubleval($post['min_value']),
                                                   'max_value' => doubleval($post['max_value']),
            );
        }
        else
        {
            $typeFilterField->values = preg_split('/\n|\r/', trim($post['menu_values']), -1, PREG_SPLIT_NO_EMPTY);
        }
        $typeFilterField->values = serialize($typeFilterField->values);
        if (strlen($typeFilterField->values) >= 4096)
        {
             setMessage('选项值太长，请适当减少选项', "error");
             return false;
        }
        return true;
    }

    public function addfilterfieldAction($type)
    {
        if (!isset($type))
        {
            setMessage('非法输入', 'error');
            gotoUrl('admin/product/typelist');
        }
        $this->view->assign("type", $type);
        if ($this->isPost())
        {
            if ($this->checkFormPara($_POST, $typeFilterField))
            {
                $whereArray = array(
                    'product_type' => $typeFilterField->product_type,
                    'attr_name' => $typeFilterField->attr_name,
                );
                if (count(TypeFilterField_Model::getInstance()->getTypeFilterFieldByArray($whereArray)) > 0)
                {
                    setMessage('添加过滤选项失败，该属性已经存在', 'error');
                }
                else
                {
                    if (!TypeFilterField_Model::getInstance()->addTypeFilterField($typeFilterField))
                    {
                        setMessage('添加过滤选项失败', 'error');
                    }
                    else
                    {
                        setMessage('添加过滤选项成功', 'notice');
                        gotoUrl('admin/product/filterfieldlist/' . $type);
                    }
                }
            }
        }
         
        $filterFieldList = $this->getFilterFieldList(1, $type);
        $this->view->render("admin/product/filterfieldnew.phtml", array(
  	    'filterFieldList' => $filterFieldList,
        ));
    }
    
    /*
    public function editfilterfieldAction($type)
    {
        if (!isset($type))
        {
            setMessage('非法输入', 'error');
            gotoUrl('admin/product/typelist');
        }
        $selfAttrList = ProductFilterAttr_Model::getInstance()->getProductFilterAttrList();
        $multiAttrList = $this->getFilterFieldList(TypeFilterField_Model::ATTR_TYPE_MULTI, $type);
        $tagTermsList = Taxonomy_Model::getInstance()->getTermsList(Taxonomy_Model::TYPE_TAG);
        $recommenedTermsList = Taxonomy_Model::getInstance()->getTermsList(Taxonomy_Model::TYPE_RECOMMEND);
        $brandTermsList = Taxonomy_Model::getInstance()->getTermsList(Taxonomy_Model::TYPE_BRAND);
        
        $this->view->assign('type', $type);
        $this->view->assign('field', null);
        $this->view->assign('selfAttrList', $selfAttrList);
        $this->view->assign('multiAttrList', $multiAttrList);
        $this->view->assign('tagTermsList', $tagTermsList);
        $this->view->assign('recommenedTermsList', $recommenedTermsList);
        $this->view->assign('brandTermsList', $brandTermsList);
        $this->view->render('admin/product/filterfieldedit.phtml');
    }*/
    
    public function editfilterfieldAction($type, $id = 0)
    {
        if (!isset($type))
        {
            setMessage('非法输入', 'error');
            gotoUrl('admin/product/typelist');
        }
        if ($id == 0)
        {
            setMessage('过滤选项id为空', 'error');
            gotoUrl('admin/product/typeedit/' . $type);
        }
        $typeFilterField = TypeFilterField_Model::getInstance()->getTypeFilterFieldById($id, $type);
        if (!isset($typeFilterField))
        {
            setMessage('未找到指定的过滤选项', 'error');
            gotoUrl('admin/product/typeedit/' . $type);
        }
        if ($this->isPost())
        {
            if ($this->checkFormPara($_POST, $updatedTypeFilterField))
            {
                if ($typeFilterField == $updatedTypeFilterField)
                {
                    setMessage('过滤选项未做任何修改，无需更新', 'notice');
                }
                else 
                {
                    if (!TypeFilterField_Model::getInstance()->editTypeFilterField($updatedTypeFilterField))
                    {
                        setMessage('修改过滤选项失败', 'error');
                    }
                    else
                    {
                        setMessage('修改过滤选项成功', 'notice');
                        gotoUrl('admin/product/filterfieldlist/' . $type);
                    }
                }
            }
        }
        $typeFilterField->values = unserialize($typeFilterField->values);
        $filterNameFieldList = $this->getFilterFieldList($typeFilterField->attr_type, $type);
        $filterValueList = $this->getFilterFieldAttrValueList($typeFilterField->attr_type, $type, $typeFilterField->attr_name);
        $this->view->assign('type', $type);
        $this->view->assign('field', $typeFilterField);
        $this->view->assign('filterFieldList', $filterNameFieldList);
        $this->view->assign('filterValueList', $filterValueList);
        $this->view->render('admin/product/filterfieldedit.phtml');
    }
    public function deletefilterfieldAction($type, $id = 0)
    {
        if (!isset($type))
        {
            setMessage('非法输入', 'error');
            gotoUrl('admin/product/typelist');
        }
        if ($id == 0)
        {
            setMessage('过滤选项id为空', 'error');
            gotoUrl('admin/product/filterfieldlist/' . $type);
        }
        if (TypeFilterField_Model::getInstance()->deleteTypeFilterField($id, $type))
        {
            setMessage('过滤选项删除成功', 'notice');
        }
        else
        {
            setMessage('过滤选项删除失败', 'error');
        }
        gotoUrl('admin/product/filterfieldlist/' . $type);
    }
    
    public function firstListAction($key)
    {
        if ($key == 'all') {
            foreach ($_SESSION['listproduct'] as $key1 => $dl) {
                unset($_SESSION['listproduct'][$key1]);
            }
        } else {
            unset($_SESSION['listproduct'][$key]);
        }
        gotoUrl('admin/product/list');
    }

    public function listAction($page = 1, $orderby = 'updated DESC, pid DESC')
    {
        if (!access(array('list product', 'administrator page'), 'or')) {
            goto403('Access Denied.');
        }
        $filter = array();
        if ($this->isPost()) {
            $post = $_POST;
            $post['tids'] = $post['directory_tid4'] ? $post['directory_tid4'] : (
            $post['directory_tid3'] ? $post['directory_tid3'] : (
            $post['directory_tid2'] ? $post['directory_tid2'] : (
            $post['directory_tid1'] ? $post['directory_tid1'] : 0
            )
            )
            );
            unset($post['directory_tid1']);
            unset($post['directory_tid2']);
            unset($post['directory_tid3']);
            unset($post['directory_tid4']);
            $filter = $post;
            foreach ($post as $key=>$dl) {
                $_SESSION['listproduct'][$key] = $dl;
            }
            $page = 1;
        }

        if(isset($_SESSION['listproduct'])){
            $filter = $_SESSION['listproduct'];
        }
        $filter['orderby'] = isset($filter['orderby']) ? $filter['orderby'] : $orderby;
        $taxonomyInstance = Taxonomy_Model::getInstance();
        $productsList = $this->_productInstance->getProductsList($filter, $page, 20);
        $productsCount = $this->_productInstance->getProductsCount($filter);
        $typeList = $this->_productInstance->getTypeList();
        $brandTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_BRAND, false);
        $brandTreeList = array();
        if (is_array($brandTermsList)) {
            foreach ($brandTermsList as $tid => $term) {
                $brandTreeList[$tid] = $term->name;
            }
        }
        $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
        $directoryTreeList = $taxonomyInstance->getTermsListForHtmlTree($directoryList);
        $directoryTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY, false);
        $selectHtml = $this->_productInstance->getSelectHtml($filter, $typeList, $directoryTermsList);
        $this->view->render('admin/product/productslist.phtml', array(
      'typeList' => $typeList,
      'productsCount' => $productsCount,
      'productsList' => $productsList,
      'brandList' => $brandTermsList,
      'brandTreeList' => $brandTreeList,
      'directoryList' => $directoryList,
      'directoryTreeList' => $directoryTreeList,
      'directoryTermsList' => $directoryTermsList,
      'selectHtml' => $selectHtml,
      'pagination' => pagination('admin/product/list/%d', $productsCount, 20, $page),
        ));
    }

    public function editAction($pid = null, $continue = false)
    {
        if (!access('edit product')) {
            goto403('Access Denied.');
        }
        $pageInstance = PageVariable_Model::getInstance();
        if (isset($pid) && !$productInfo = $this->_productInstance->getProductInfo($pid)) {
            setMessage('Product ID <em>' . $pid . '</em> not found.');
            gotoUrl('admin/product/list');
        }
        if (isset($productInfo->pvid)) {
            $productInfo->pv = $pageInstance->selectPageVariables($productInfo->pvid, 'product', $productInfo);
        }
        $pvThemes = $pageInstance->getPageVariablesThemeList();
        $this->view->assign('pvThemes', $pvThemes);
        $userInstance = User_Model::getInstance();
        $ranks = $userInstance->getRanksList();
        $fileInstance = File_Model::getInstance();
        $isnew = !isset($pid) || $continue;
        if ($this->isPost()) {
            $post = (object)$_POST;
            if (isset($pid)) {
                $post->pid = $productInfo->pid;
            }
            //最大数，最小数的判断 sell_min sell_max
            if ($post->sell_min && $post->sell_max && ($post->sell_min > $post->sell_max)) {
                setMessage('商品最大数不能小于最小数', 'error');
                $reffer_url = $_SERVER["HTTP_REFERER"];
                header("Location: ".$reffer_url);
                exit;
            }

            $post->brand_tid = isset($post->brand) ? intval($post->brand) : 0;
            $productInfo = $post;
            if (!isset($productInfo->type) || !$typeInfo = $this->_productInstance->getTypeInfo(trim($productInfo->type))) {
                setMessage('商品类型不存在', 'error');
            } else if (!isset($productInfo->name) || trim($productInfo->name) == '') {
                setMessage('商品名称错误', 'error');
            } else if (!isset($productInfo->sell_price) || !is_numeric($productInfo->sell_price)) {
                setMessage('商品销售价格必须为数字', 'error');
            } else {
                $type = $typeInfo->type;
                if(isset($productInfo->directory_tid)){
                	$directory=$productInfo->directory_tid;
                	for($i=0;$i<count($directory);$i+=5){
                		$subdir=array_slice($directory, $i,5);
                		$tid=0;
                		if($productInfo->pid){
                			if($subdir[4]){
                				$tid=intval($subdir[4]);
                			}
                			else if($subdir[3]){
                				$tid=intval($subdir[3]);
                			}
                			else if($subdir[2]){
                				$tid=intval($subdir[2]);
                			}
                			else if($subdir[1]){
                				$tid=intval($subdir[1]);
                			}
                			 
                			if($subdir[0]){
                				if($tid){
                					$this->_productInstance->updataProcduct_Directory($productInfo->pid, $tid, $subdir[0]);
                				}
                				else{
                					$this->_productInstance->deleteProduct_Directory($subdir[0]);
                				}
                			}
                			else{
                				if($tid){
                					$this->_productInstance->insertProduct_Directory($productInfo->pid, $tid);
                				}
                			}
                		}
                	}
                }
                if (!isset($productInfo->path_alias) || trim($productInfo->path_alias) == '') {
                    $commonInstance = Common_Model::getInstance();
                    $pathAlias = $commonInstance->callFunction('translate', trim($productInfo->name));
                } else {
                    $pathAlias = trim($productInfo->path_alias);
                }
                // 检查重复的路径别名, 自动加数字后缀
                $pathAliases = $this->_productInstance->getProductPathAliasList($pathAlias);
                if (!empty($pathAliases) && ($isnew || $pid != array_search($pathAlias, $pathAliases))) {
                    $n = 1;
                    while(array_search($pathAlias . '-' . $n, $pathAliases)) {
                        ++$n;
                    }
                    $pathAlias .= '-' . $n;
                }
                $set = array(
          'type' => $type,
          'sn' => trim($productInfo->sn),
          'number' => trim($productInfo->number),
          'name' => trim($productInfo->name),
          'status' => isset($productInfo->status) ? 1 : 0,
          'shippable' => isset($productInfo->shippable) ? 1 : 0,
          'free_shipping' => isset($productInfo->free_shipping) ? 1 : 0,
          'brand_tid' => $productInfo->brand_tid,
          'sell_price' => $productInfo->sell_price,
          'list_price' => $productInfo->list_price,
          'wt' => $productInfo->wt,
          'stock' => intval($productInfo->stock),
          'sell_min' => isset($productInfo->sell_min) ? intval($productInfo->sell_min) : 0,
          'sell_max' => isset($productInfo->sell_max) ? intval($productInfo->sell_max) : 0,
          'summary' => trim($productInfo->summary),
          'description' => trim($productInfo->description),
          'path_alias' => $pathAlias,
          'template' => trim($productInfo->template),
          'visible' => !isset($productInfo->visible) || $productInfo->visible,
          'weight' => isset($productInfo->weight) ? intval($productInfo->weight) : 0,
          'customizable' => isset($productInfo->customizable) ? 1 : 0,
          'sphinx_key' => isset($productInfo->search_key) ? $productInfo->search_key : '',
          'videopath' => isset($productInfo->videopath) ? $productInfo->videopath : '',
                );
                // 图片上传
                if (isset($_FILES['file']) && !$_FILES['file']['error'] && $_FILES['file']['size']) {
                    $filepost = array('type' => 'product');
                    $file = $fileInstance->insertFile('file', $filepost);
                    $set['fid'] = $file->fid;
                    $set['filepath'] = $file->filepath;
                    $productInfo->filepath = $file->filepath;
                }
                if (($_POST['files'] == '[]' || $_POST['files'] == '{}') && (!isset($file) || !$file) && (!$post->fid)) {
                    setMessage('商品至少需要有一张图片', 'error');
                } else {
                    if (isset($_POST['pvTheme']) && $_POST['pvTheme']) {
                        $set['pvid'] = $_POST['pvTheme'];
                    } else {
                        if ($_POST['pvid']) {
                            $set['pvid'] = $pageInstance->updatePageVariables($_POST['pvid'], $_POST);
                        } else {
                            $pvid = $pageInstance->insertPageVariables($_POST);
                            if ($pvid) {
                                $set['pvid'] = $pvid;
                            }
                        }
                    }
                    if (!$isnew) {
                        $this->_productInstance->updateProduct($pid, $set);
                        $this->_productInstance->updateProductFields($pid, $type, $post);
                        $cacheId = 'product-' . $pid;
                        cache::remove($cacheId);
                        setMessage('修改商品成功', 'notice');
                        $url = 'admin/product/list';
                    } else {
                         
                        log::save("ADMINDEBUG", "Add Product Succeed", "Add Product Succeed.");
                         
                        $pid = $this->_productInstance->insertProduct($set);
                        if ($pid) {
                            $this->_productInstance->insertProductFields($pid, $type, $post);
                            callFunction('ping');
                            setMessage('新增商品成功', 'notice');
                        } else {
                            setMessage('商品新建失败', 'error');
                        }
                        if (isset($productInfo->continue) && $productInfo->continue) {
                            $url = 'admin/product/edit/' . $pid . '/new';
                        } else {
                            $url = 'admin/product/list';
                        }
                    }
                    // 公共处理方法
                    if ($pid) {
                        $post->terms_products = isset($post->terms_products) ? $post->terms_products : null;
                        $this->_productInstance->updateProductTerms($pid, $post->terms_products);
                        // 保存关系
                        if(($_POST['related_info'] != '{}' && $isnew) || (!$isnew)) {
                            $related_info = strtr(stripcslashes($_POST['related_info']), '\'', '"');
                            $related_info = json_decode($related_info);
                            $this->_productInstance->updateProductRelated($pid, $related_info);
                        }
                        // 保存图库
                        if($_POST['files']){
                            $files_info = strtr(stripcslashes($_POST['files']), '\'', '"');
                            $files_info = json_decode($files_info);
                            if (isset($files_info) && $files_info) {
                                foreach($files_info as $k => $v){
                                    $ii = isset($ii) ? $ii : $v->weight;
                                    $file = isset($file) ? $file : $v;
                                    if ($v->weight > $ii) {
                                        $file = $v;
                                    }
                                }
                            }
                            if (isset($file->fid) && $file->fid) {
                                $this->_productInstance->updateProductFile($pid, $file->fid);
                            }
                            $this->_productInstance->updateProductFiles($pid, $files_info);
                        }
                        // 保存会员价格
                        $this->_productInstance->deleteProductRanksPrice($pid);
                        if (isset($_POST['rank_price_check']) && !empty($_POST['rank_price_check'])) {
                            $set = array();
                            foreach ($_POST['rank_price'] as $rid => $price) {
                                if (isset($_POST['rank_price_check'][$rid]) && is_numeric(trim($price))) {
                                    $set[$rid] = trim($price);
                                }
                            }
                            $this->_productInstance->insertProductRanksPrice($pid, $set);
                        }
                    }
                    gotoUrl($url);
                }
            }
        } else {
            if (isset($pid)) {
                $type = $productInfo->type;
                $this->view->assign('typeInfo', $this->_productInstance->getTypeInfo($type));
                $fieldsList = $this->_productInstance->getTypeFieldsList($type);
                foreach ($fieldsList as $fieldName => &$field) {
                    $field->widget = $this->_productInstance->getTypeFieldWidget($field, isset($productInfo) &&
                    isset($productInfo->{'field_' . $fieldName}) ? $productInfo->{'field_' . $fieldName} : null,
                    $field->valued && isset($productInfo) && isset($productInfo->{'field_' . $fieldName . '_value'}) ? $productInfo->{'field_' . $fieldName . '_value'} : null);
                    $field->can_add = $this->_productInstance->getTypeFieldHasMultipleInput($field);
                }
                $this->view->assign('fieldsList', $fieldsList);
                /*<< PowerBY weijt 20100817 图库-商品关联*/
                $productsRelatedList = $this->_productInstance->listProductRelated($pid);
                if (isset($productsRelatedList)) {
                    foreach($productsRelatedList as $key => $dl){
                        $new_relatedList[$key]->pid = $productsRelatedList[$key]->pid;
                        $new_relatedList[$key]->name = $productsRelatedList[$key]->name;
                        $new_relatedList[$key]->isbothway = $productsRelatedList[$key]->isbothway;
                    }
                }
                isset($new_relatedList) ?  $relatedListJson = strtr(json_encode($new_relatedList), '"', '\'') : $relatedListJson = "{}";
                $fileModel = File_Model::getInstance();
                if (!$continue) {
                    $filesList = $this->_productInstance->getProductFilesList($pid);
                }
                $showInColorBoxFiles = '';
                if (isset($filesList) && $filesList) {
                    foreach ($filesList as $key => $dl) {
                        $fileListSimple[$key] = new stdClass();
                        $fileListSimple[$key]->fid = $filesList[$key]->fid;
                        $fileListSimple[$key]->alt = $filesList[$key]->alt;
                        $fileListSimple[$key]->weight = $filesList[$key]->weight;
                        $fileListSimple[$key]->show_in_color_box = $filesList[$key]->show_in_color_box;
                    }
                }
                /*>>*/
                // 载入会员等级价格
                $ranksPrice = $this->_productInstance->getProductRanksPrice($pid);
                $this->view->assign('ranksPrice', $ranksPrice);
                $productInfo->terms_products = $this->_productInstance->getProductTerms($pid);
            }
        }
        
        $productInfo->terminfo_list=$this->_productInstance->getProductTermsInfo($pid);
        $typeList = $this->_productInstance->getTypeList();
        $taxonomyInstance = Taxonomy_Model::getInstance();
        $brandTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_BRAND, false);
        $directoryList = $taxonomyInstance->getTermsList(Taxonomy_Model::TYPE_DIRECTORY);
        /*************************************************************************/
        //添加标签选择列表
        $vocabularyList = array();  // 商品分类
        $termsList = array();       // 所有分类词列表
        $productTerms = array();    // 商品所属分类词
        $vocabularyList = $this->_taxonomyModel->getVocabularyList();
        foreach ($vocabularyList AS $vid => $vocabulary) {
            if ($vid == Taxonomy_Model::TYPE_BRAND || $vid == Taxonomy_Model::TYPE_DIRECTORY) {
                continue;
            }
            $termsList[$vid] = $this->_taxonomyModel->getTermsList($vid, false);
        }
        if (isset($productInfo) && !isset($productInfo->terms_products)) {
            $productInfo->terms_products = array();
        }
        /*************************************************************************/

        $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
        $this->view->addCss(url('styles/themes/base/jquery.ui.datepicker.css'));
        $this->view->addJs(url('scripts/swfupload-jquery/vendor/swfupload/swfupload.js'));
        $this->view->addJs(url('scripts/swfupload-jquery/src/jquery.swfupload.js'));
        $this->view->addCss(url('scripts/swfupload-jquery/css/default.css'));
        $productsList = $this->_productInstance->getProductsList(null, 1, 20);
        $productInfo = isset($productInfo) ? $productInfo : null;
        $productsRelatedList = isset($productsRelatedList) ? $productsRelatedList : null;
        $filesList = isset($filesList) ? $filesList : null;
        $fileListSimple = isset($fileListSimple) ? $fileListSimple : array();
        $relatedListJson = isset($relatedListJson) ? $relatedListJson : '{}';
        //获取会员折扣
        //$ranks = $userInstance->getRanksList();
        //获取市场价配置
        $markprice = Bl_Config::get('marketprice', array('marketprice' => '150%'));
        $markprice = $markprice['marketprice'];
        $this->view->render('admin/product/productinfo.phtml', array(
      'isnew' => $isnew,
      'continue' => $continue,
      'product' => $productInfo,
      'typeList' => $typeList,
      'brandList' => $brandTermsList,
      'directoryList' => $directoryList,
      'filesList' => $filesList,//PowerBY weijt 20100817 图库
      'fileListSimple' => $fileListSimple,//PowerBY weijt 20100817 图库
      'productsList' => $productsList,//PowerBY weijt 20100817 商品关联
      'productsRelatedList' => $productsRelatedList,
      'relatedListJson' => $relatedListJson,
      'ranks' => $ranks,
      'pv' => isset($productInfo->pv) ? $productInfo->pv : null,
      'vocabularyList' => $vocabularyList,
      'termsList' => $termsList,
      'markprice' => $markprice,
        ));
    }

    public function copyAction($pid = null) {
    	if (!access('copy product')) {
    		goto403('Access Denied.');
    	}
    	if (!$productInfo = $this->_productInstance->getProductInfo($pid)) {
    		setMessage('Product ID <em>' . $pid . '</em> not found.');
    		gotoUrl('admin/product/list');
    	}
    	$taxonomyInstance = Taxonomy_Model::getInstance();
    	$directoryList = $taxonomyInstance->getTermsList(Taxonomy_Model::TYPE_DIRECTORY);
    	if ($this->isPost()) {
    		$post = $_POST;
    		$productInstance = Product_Model::getInstance();
    		$productInfo->directory_tid1 = $post['directory_tid1'];
    		$productInfo->directory_tid2 = $post['directory_tid2'];
    		$productInfo->directory_tid3 = $post['directory_tid3'];
    		$productInfo->directory_tid4 = $post['directory_tid4'];
    		$productInstance->copyProduct($productInfo);
    	}
    	$this->view->render('admin/product/copy.phtml', array(
    		'product' => $productInfo,
    		'directoryList' => $directoryList,
    	));
    }
    
    public function batchcopyAction() {
    	if (!access('batch copy product')) {
    		goto403('Access Denied');
    	}
    	$taxonomyInstance = Taxonomy_Model::getInstance();
    	$directoryList = $taxonomyInstance->getTermsList(Taxonomy_Model::TYPE_DIRECTORY);
    	if ($this->isPost()) {
    		$post = $_POST;
    		if (empty($post['product_sn_list'])) {
    			setMessage('请输入需要复制目录的产品sn', 'error');
    		} else {
	    		$productInstance = Product_Model::getInstance();
	    		$productSnList = preg_split('/\r\n|\r|\n/', trim($post['product_sn_list']));
	    		$success = true;
	    		foreach ($productSnList as $sn) {
	    			if (!$productInfo = $this->_productInstance->getProductInfoBySn($sn)) {
	    				setMessage('产品' . $sn . '没有找到.', 'error');
	    				$success = false;
	    				continue;
	    			}
	    			$productInfo->directory_tid1 = $post['directory_tid1'];
	    			$productInfo->directory_tid2 = $post['directory_tid2'];
	    			$productInfo->directory_tid3 = $post['directory_tid3'];
	    			$productInfo->directory_tid4 = $post['directory_tid4'];
	    			if (!$productInstance->copyProduct($productInfo)) {
	    				setMessage('复制产品' . $sn . '失败.', 'error');
	    				$success = false;
	    			}
	    		}
	    		if ($success) {
	    			setMessage('复制产品成功');
	    		} else {
	    			setMessage('复制产品失败，请核对出错信息中的sn是否正确.',   'error');
	    		}
    		}
    	}
    	$this->view->render('admin/product/batchcopy.phtml', array(
    			'directoryList' => $directoryList,
    	));
    }
    
    public function batchaddtagAction() {
		if (!access('batch add product tag')) {
			goto403('Access Denied');
		}
		$taxonomyInstance = Taxonomy_Model::getInstance();
		$tagList = $taxonomyInstance->getTermsList(Taxonomy_Model::TYPE_TAG);
		while($this->isPost()) {
			$post = $_POST;
    		if (empty($post['product_sn_list'])) {
    			setMessage('请输入需要复制目录的产品sn', 'error');
    			break;
    		}
			if (empty($post['tag_list'])) {
				setMessage('请选择产品的tag', 'error');
				break;
			}
	    	$productInstance = Product_Model::getInstance();
	    	$productSnList = preg_split('/\r\n|\r|\n/', trim($post['product_sn_list']));
	    	$success = true;
	    	foreach ($productSnList as $sn) {
	    		if (!$productInfo = $this->_productInstance->getProductInfoBySn($sn)) {
	    			setMessage('产品' . $sn . '没有找到.', 'error');
	    			$success = false;
	    			continue;
	    		}
	    		foreach ($post['tag_list'] as $tid) {
		    		$productInstance->insertProductTerms($productInfo->pid, $tid);
	    		}
    		}
    		if ($success) {
    			setMessage('批量添加产品tag成功');
    		} else {
    			setMessage('批量添加产品tag失败，请核对出错信息中的sn是否正确.',   'error');
    		}
    		break;
		}
    	$this->view->render('admin/product/batchaddtag.phtml', array('tagList' => $tagList));
    }
    
    public function batchadddirectoryAction(){
    	$taxonomyInstance = Taxonomy_Model::getInstance();
    	$directoryList = $taxonomyInstance->getTermsList(Taxonomy_Model::TYPE_DIRECTORY);
    
    	while($this->isPost()) {
    		$post = (object)$_POST;
    		if (empty($post->product_url_list)) {
    			setMessage('请输入需要复制目录的产品sn', 'error');
    			break;
    		}
    		$productInstance = Product_Model::getInstance();
    		$productUrlList = preg_split('/\r\n|\r|\n/', trim($post->product_url_list));
    		$success = true;
    		if(isset($post->directory_tid)){
    			$subdir=$post->directory_tid;
    			$tid=0;
    			if($subdir[3]){
    				$tid=intval($subdir[3]);
    			}
    			else if($subdir[2]){
    				$tid=intval($subdir[2]);
    			}
    			else if($subdir[1]){
    				$tid=intval($subdir[1]);
    			}
    			else if($subdir[0]){
    				$tid=intval($subdir[0]);
    			}
    		}
    		if(!$tid){
    			setMessage('请选择目录','error');
    			break;
    		}
    		foreach ($productUrlList as $uri) {
    			$uri = trim(preg_replace('/\/{2,}/', '/', $uri), '/');
    			$success=false;
    			if(endsWith($uri, '.html')){
    				//split by -p, then get the last one.
    				$success=true;
    				$splits = explode('-p', $uri);
    				if(count($splits) > 1){
    					$lastSeg = $splits[count($splits)-1];
    					if(preg_match('/^\d+(-\d+)?.html/', $lastSeg, $matches) > 0){
    						//no branding.
    						$sn = substr($matches[0], 0, strlen($matches[0]) - 5);
    					}
    					else if(preg_match('/^[A-Z]{2}_.*.html/', $lastSeg, $matches) > 0){
    						//branding.
    						$sn = substr($matches[0], 0, strlen($matches[0]) - 5);
    					}
    				}
    
    				if (!$productInfo = $this->_productInstance->getProductInfoBySn($sn)) {
    					setMessage('产品' . $sn . '没有找到.', 'error');
    					$success = false;
    					continue;
    				}
    				$this->_productInstance->insertProduct_Directory($productInfo->pid, $tid);
    			}
    		}
    		if ($success) {
    			setMessage('批量加入目录成功');
    		} else {
    			setMessage('批量加入目录失败，请核对出错信息中的sn是否正确.',   'error');
    		}
    		break;
    	}
    	$this->view->render('admin/product/batchadddirectory.phtml',array('directoryList'=>$directoryList));
    }
    
    public function deleteAction($pid)
    {
        if (!access('delete product')) {
            goto403('Access Denied.');
        }
        if (!$productInfo = $this->_productInstance->getProductInfo($pid)) {
            setMessage('Product ID <em>' . $pid . '</em> not found.');
        } else {
            $this->_productInstance->deleteProduct($pid);
        }
        gotoUrl('admin/product/list');
    }

    public function deletecacheAction($pid){
    	if (!access('delete product image cache')) {
    		goto403('Access Denied.');
    	}
    	
    	if($this->_productInstance->deleteProductCacheImage($pid)){
    		setMessage('删除缓存成功！');
    	} else {
    		setMessage('删除缓存失败！', 'error');
    	}
    	gotoUrl('admin/product/list');
    }
    public function editfieldAction($pid, $field)
    {
        if (!access('edit product')) {
            goto403('Access Denied.');
        }
        $fieldMap = array(
      'sn' => 'sn',
      'nu' => 'number',
      'na' => 'name',
      'dt' => 'directory_tid',
      'sp' => 'sell_price',
      'st' => 'stock',
      'sa' => 'status',
      'bt' => 'brand_tid',
      'wt' => 'weight',
        );
        $json = array(
      'error' => 1,
      'pid' => $pid,
      'field' => $field,
      'msg' => '修改失败',
        );
        $value = trim($_POST['value']);
        if(!strpos($pid, "dp")){
        	if (isset($fieldMap[$field])) {
        		$validated = true;
        		if ($value && $field == 'dt') {
        			$taxonomyInstance = Taxonomy_Model::getInstance();
        			$term = $taxonomyInstance->getTermInfo($value);
        			if ($term) {
        				$set['directory_tid1'] = $set['directory_tid2'] = $set['directory_tid3'] = $set['directory_tid4'] = 0;
        				if (!$term->ptid1) {
        					$set['directory_tid1'] = $term->tid;
        				} else if (!$term->ptid2) {
        					$set['directory_tid1'] = $term->ptid1;
        					$set['directory_tid2'] = $term->tid;
        				} else if (!$term->ptid3) {
        					$set['directory_tid1'] = $term->ptid1;
        					$set['directory_tid2'] = $term->ptid2;
        					$set['directory_tid3'] = $term->tid;
        				} else {
        					$set['directory_tid1'] = $term->ptid1;
        					$set['directory_tid2'] = $term->ptid2;
        					$set['directory_tid3'] = $term->ptid3;
        					$set['directory_tid4'] = $term->tid;
        				}
        			}
        		} else if ($value && $field == 'bt') {
        			$taxonomyInstance = Taxonomy_Model::getInstance();
        			$term = $taxonomyInstance->getTermInfo($value);
        			if (!$term) {
        				$validated = false;
        			}
        		} else if ($field == 'sa') {
        			$value = $value == 2 ? 1 : 0;
        		}
        		$set = isset($set) ? $set : array($fieldMap[$field] => $value);
        		if ($validated && $this->_productInstance->updateProduct($pid, $set)) {
        			$json['error'] = 0;
        			if ($field == 'bt' || $field == 'dt') {
        				$value = $term->name;
        			} else if ($field == 'sa') {
        				$value = $value ? t('Published') : t('Unpublished');
        			}
        			$json['value'] = $value;
        			unset($json['msg']);
        		}
        	}
        	echo json_encode($json);
        }
        else{
        	$this->ajaxproductdirectoryweightAction($pid, $value);
        }
    }

    public function getfieldsAction($type, $pid = null)
    {
        if (!access('edit product')) {
            goto403('Access Denied.');
        }
        if (!$typeInfo = $this->_productInstance->getTypeInfo($type)) {
            return '';
        }
        if (!isset($pid) || !$productInfo = $this->_productInstance->getProductInfo($pid)) {
            $productInfo = null;
        }
        $fieldsList = $this->_productInstance->getTypeFieldsList($type);
        foreach ($fieldsList as $fieldName => &$field) {
            $field->widget = $this->_productInstance->getTypeFieldWidget($field, isset($productInfo) &&
            isset($productInfo->{'field_' . $fieldName}) ? $productInfo->{'field_' . $fieldName} : null);
            $field->can_add = $this->_productInstance->getTypeFieldHasMultipleInput($field);
        }

        $this->view->render('admin/product/getfields.phtml', array(
      'type' => $typeInfo,
      'product' => $productInfo,
      'fieldsList' => $fieldsList,
        ));
    }

    public function settingAction()
    {
        if (!access('setting')) {
            goto403('Access Denied.');
        }
        if ($this->isPost()) {
            $isProductPublishAuto = isset($_POST['isProductPublishAuto']) ? $_POST['isProductPublishAuto'] : "";
            Bl_Config::set('isProductPublishAuto', $isProductPublishAuto);
            Bl_Config::save();
            setMessage('设置成功');
            gotoUrl('admin/product/setting');
        } else {
            $isProductPublishAuto = Bl_Config::get('isProductPublishAuto', 1);
            $this->view->render('admin/product/setting.phtml', array(
        'isProductPublishAuto' => $isProductPublishAuto,
            ));
        }
    }

    public function promotionslistAction()
    {
        if (!access('list promotions')) {
            goto403('Access Denied.');
        }
        $promotionsList = $this->_productInstance->getPromotionsList();
        $this->view->render('admin/product/promotionslist.phtml', array(
      'promotionsList' => $promotionsList,
        ));
    }

    public function promotioneditAction($pmid = null)
    {
        if (!access('list promotions')) {
            goto403('Access Denied.');
        }
        if (isset($pmid) && !$promotionInfo = $this->_productInstance->getPromotionInfo($pmid)) {
            setMessage('Promotion <em>' . $pmid . '</em> not found.');
            gotoUrl('admin/product/promotionslist');
        }

        if ($this->isPost()) {
            $promotionInfo = (object)$_POST;

            $startTime = 0;
            if(isset($promotionInfo->start_time) && trim($promotionInfo->start_time) != ''){
                $startTime = strtotime($promotionInfo->start_time);
                $startTime = false == $startTime ? 0 : $startTime;
                $promotionInfo->start_time = $startTime;
            }

            $endTime = 0;
            if(isset($promotionInfo->end_time) && trim($promotionInfo->end_time) != ''){
                $endTime = strtotime($promotionInfo->end_time);
                $endTime = false == $endTime ? 0 : $endTime;
                $promotionInfo->end_time = $endTime;
            }

            if (!isset($promotionInfo->name) || trim($promotionInfo->name) == '') {
                setMessage('活动名称错误', 'error');
            } else if ($startTime == 0) {
                setMessage('活动开始时间错误', 'error');
            } else if ($endTime == 0) {
                setMessage('活动结束时间错误', 'error');
            }else if($startTime > $endTime){
                setMessage('活动开始时间须小于结束时间', 'error');
            } else {
                if (!isset($promotionInfo->path_alias) || trim($promotionInfo->path_alias) == '') {
                    $commonInstance = Common_Model::getInstance();
                    $pathAlias = $commonInstance->callFunction('translate', trim($promotionInfo->name));
                } else {
                    $pathAlias = trim($promotionInfo->path_alias);
                }
                // 检查重复的路径别名, 自动加数字后缀
                $pathAliases = $this->_productInstance->getPromotionPathAliasList($pathAlias);
                if (!empty($pathAliases) && ($isnew || $pmid != array_search($pathAlias, $pathAliases))) {
                    $n = 1;
                    while(array_search($pathAlias . '-' . $n, $pathAliases)) {
                        ++$n;
                    }
                    $pathAlias .= '-' . $n;
                }
                $set = array(
          'name' => trim($promotionInfo->name),
          'description' => trim($promotionInfo->description),
          'start_time' => $startTime,
          'end_time' => $endTime,
          'status' => isset($promotionInfo->status) ? 1 : 0,
          'path_alias' => $pathAlias,
          'template' => trim($promotionInfo->template),
          'weight' => isset($promotionInfo->weight) ? intval($promotionInfo->weight) : 0,
                );
                /*<< 修改页面信息  Power by WEIJT*/
                $pageInstance = PageVariable_Model::getInstance();
                if (isset($_POST['pvTheme']) && $_POST['pvTheme']) {
                    $set['pvid'] = $_POST['pvTheme'];
                } else {
                    if ($_POST['pvid']) {
                        $set['pvid'] = $pageInstance->updatePageVariables($_POST['pvid'], $_POST);
                    } else {
                        $pvid = $pageInstance->insertPageVariables($_POST);
                        $set['pvid'] = $pvid;
                    }
                }
                /*修改页面信息  Power by WEIJT >>*/
                if (isset($pmid)) {
                    $this->_productInstance->updatePromotion($pmid, $set);
                } else {
                    $this->_productInstance->insertPromotion($set);
                }
                gotoUrl('admin/product/promotionslist');
            }
        } else {
            $pageInstance = PageVariable_Model::getInstance();
            if (!isset($pmid)) {
                $promotionInfo = null;
            } else {
                if (isset($promotionInfo->pvid)) {
                    $promotionInfo->pv = $pageInstance->selectPageVariables($promotionInfo->pvid, 'promotion', $promotionInfo);
                }
            }
            $pvThemes = $pageInstance->getPageVariablesThemeList();
            $this->view->assign('pvThemes', $pvThemes);
        }

        $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
        $this->view->addCss(url('styles/themes/base/jquery.ui.datepicker.css'));
        $this->view->render('admin/product/promotioninfo.phtml', array(
      'isnew' => !isset($pmid),
      'promotion' => $promotionInfo,
      'pv' => isset($promotionInfo->pv) ? $promotionInfo->pv : null,
        ));
    }

    public function promotiondeleteAction($pmid)
    {
        if (!access('list promotions')) {
            goto403('Access Denied.');
        }
        if (!$promotionInfo = $this->_productInstance->getPromotionInfo($pmid)) {
            setMessage('Promotion <em>' . $pmid . '</em> not found.');
        } else {
            setMessage('Promotion <em>' . $pmid . '</em> had deleted.');
            $this->_productInstance->deletePromotion($pmid);
        }
        gotoUrl('admin/product/promotionslist');
    }

    /**
     * 设置促销活动商品，路径跳转
     * 当促销活动已添加有商品时跳转到执行 promotionproductslistAction() 函数
     * 当没有商品时跳转执行 promotionproductssetAction() 函数
     *
     * 2010-10-15 Added By 55Feng
     *
     * @param $pmid
     */
    public function promotionproductsAction($pmid=null)
    {
        if (!access('list promotions')) {
            goto403('Access Denied.');
        }
        if (isset($pmid) && !$promotionInfo = $this->_productInstance->getPromotionInfo($pmid)) {
            setMessage('Promotion <em>' . $pmid . '</em> not found.');
            gotoUrl('admin/product/promotionslist');
        }

        $pidList = $this->_productInstance->getPromotionPidList($pmid);
        if( !is_array($pidList) || count($pidList)<1 ){
            $url = 'admin/product/promotionproductset/'.$pmid;
        }else{
            $url = 'admin/product/promotionproductlist/'.$pmid;
        }
        gotoUrl($url);
    }

    /**
     * 设置促销活动商品，商品添加和删除， 2010-10-15 Added By 55Feng
     * @param $pmid
     */
    public function promotionproductsetAction($pmid=null)
    {
        if (!access('list promotions')) {
            goto403('Access Denied.');
        }
        if (isset($pmid) && !$promotionInfo = $this->_productInstance->getPromotionInfo($pmid)) {
            setMessage('Promotion <em>' . $pmid . '</em> not found.', 'error');
            gotoUrl('admin/product/promotionslist');
        }

        //保存提交过来的商品列表
        if ($this->isPost()) {
            $newPidList = json_decode($_POST['BatchPidList']);
            if(!is_array($newPidList)){
                $newPidList = array();
            }
            $oldPidList = $this->_productInstance->getPromotionPidList($pmid);
            //删除旧的活动商品
            foreach($oldPidList as $kye=>$pid ){
                if(!in_array($pid, $newPidList)){
                    $this->_productInstance->deletePromotionProduct($pmid, $pid);
                }
            }
            //添加新的活动商品
            $pidList = array();
            foreach( $newPidList as $key=>$pid ){
                if(!in_array($pid, $oldPidList)){
                    $pidList[] = $pid;
                }
            }
            $this->_productInstance->addPromotionProduct($pmid, $pidList);
            $url = 'admin/product/promotionproducts/'.$pmid;
            gotoUrl($url);
        } // end isPost()

        $directoryList = $this->_taxonomyModel->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);

        //查询活加已添加的商品列表
        $pidList = $this->_productInstance->getPromotionPidList($pmid);
        $productList = $this->_productInstance->getProductsNameByPIDList($pidList);
        $this->view->render('admin/product/promotionproductset.phtml', array(
      'promotion' => $promotionInfo,
      'directoryList' => $directoryList,
      'productList' => $productList,
        ));
    }

    /**
     * 促销活动商品列表，修改活动商品价格， 2010-10-15 Added By 55Feng
     * @param int $pmid
     */
    public function promotionproductlistAction($pmid)
    {
        if (!access('list promotions')) {
            goto403('Access Denied.');
        }
        if (isset($pmid) && !$promotionInfo = $this->_productInstance->getPromotionInfo($pmid)) {
            setMessage('Promotion <em>' . $pmid . '</em> not found.', 'error');
            gotoUrl('admin/product/promotionslist');
        }
        $userInstance = User_Model::getInstance();
        $ranks = $userInstance->getRanksList();

        /*
         * 保存商品列表的价格
         * 对于 RANK_MEMBER 的直接保存
         *
         * 对于非 RANK_MEMBER 的先全部删除，再写入新选上的
         *
         * promotions_products --> pmid  pid   rid   price
         */
        if ($this->isPost()) {
            $pidList = $_POST['product'];
            $priceList = $_POST['price'];
            foreach ($pidList as $pid) {
                foreach($ranks as $key=>$rank){
                    $rid = $rank->rid;
                    $setted = false;
                    $price = 0;

                    if(isset($priceList[$pid][$rid])){
                        $setted = true;
                        $price = $priceList[$pid][$rid];
                    }

                    if ($rid == User_Model::RANK_MEMBER) {
                        //修改RANK_MEMBER 的商品价格
                        $this->_productInstance->updatePromotionPrice($pmid, $pid, $rid, $price);
                    }else{
                        //删除所有非 URANK_MEMBER 的商品
                        $this->_productInstance->deletePromotionProduct($pmid, $pid, $rid);
                        if($setted){
                            $this->_productInstance->insertPromotionPrice($pmid, $pid, $rid, $price);
                        }
                    }
                }
            }
            setMessage('保存成功');
            $url = 'admin/product/promotionproductlist/'.$pmid;
            gotoUrl($url);

        } // end isPost()


        /*
         * 列表显示
         * 先查出 ranks 表所有数据
         * 再分不同的 rid 查出此 pmid 在 promotions_products 里面的所有数据, 联合 products 顺便查出商品名称
         *
         * 再View：遍历所有 rid 为 RANK_MEMBER 的 promotions_products 数据, 在里面再遍历 ranks 数据，除 RANK_MEMBER 外
         *         其余的按编辑商品时的样子列出来，于 promotions_products 相应 rid 里面有数据的就显示为可编辑，没数据
         *         的就不可编辑
         *
         */
        $productList = array();
        foreach($ranks as $key=>$rank){
            $rid = $rank->rid;
            $productList[$rid] = $this->_productInstance->getPromotionProductsByRID($pmid, $rid);
        }

        $this->view->render('admin/product/promotionproductslist.phtml', array(
      'promotion' => $promotionInfo,
      'ranks' => $ranks,
      'productList' => $productList,
        ));

    }


    public function batchuploadAction()
    {
        if (!access('batch uplode')) {
            goto403('Access Denied.');
        }
        $userInstance = User_Model::getInstance();
        $ranks = $userInstance->getRanksList();
        $typeList = $this->_productInstance->getTypeList();
        $taxonomyInstance = Taxonomy_Model::getInstance();
        $brandTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_BRAND, false);
        $brandTreeList = array();
        if (is_array($brandTreeList)) {
            foreach ($brandTermsList as $tid => $term) {
                $brandTreeList[$tid] = $term->name;
            }
        }
        $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
        $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
        $this->view->addCss(url('styles/themes/base/jquery.ui.datepicker.css'));
        $this->view->addJs(url('scripts/swfupload-jquery/vendor/swfupload/swfupload.js'));
        $this->view->addJs(url('scripts/swfupload-jquery/src/jquery.swfupload.js'));
        $this->view->addCss(url('scripts/swfupload-jquery/css/default.css'));
        $this->view->render('admin/product/batchupload.phtml', array(
      'ranks' => $ranks,
      'typeList' => $typeList,
      'brandList' => $brandTermsList,
      'directoryList' => $directoryList,
        ));
    }

    public function uploadProductAction()
    {
        if (isset($_POST['PHPSESSID']) && $_POST['PHPSESSID']) {
            session::read($_POST['PHPSESSID']);
        }
        global $user;
        if (!access('edit product')) {
            goto403('Access Denied.');
        }
        if (isset($_FILES['filedata'])) {

            $post = array('type' => 'product');
            $fileInstance = File_Model::getInstance();
            $file = $fileInstance->insertFile('filedata', $post);
            if (!isset($_POST['name'])) {
                echo -2;exit;
            }
            if ($file) {
                $post = $_POST;
                $post['admin_tids'] = $post['tids'];
                $fileinfo = pathinfo($file->filename);

                $filearr = explode('--', $fileinfo['filename']);
                $filename = $filearr[0] . '(--.*)*\.' . $fileinfo['extension'];
                $weight = isset($filearr[1]) ? intval($filearr[1]) : 10000;
                $status = true;
                //        $pid = $this->_productInstance->getProductIdByFile($filename);
                //        if (isset($pid) && $pid) {
                //          $post['fid'] = $file->fid;
                //          $post['weight'] = $weight;
                //          $this->_productInstance->insertProductFiles($pid, $post);
                //          if (count($filearr) == 1) {
                //            $this->_productInstance->updateProduct($pid, array('filepath' => url($file->filepath, false)));
                //          }
                //          $status = false;
                //        }

                if ($status) {
                    $post['directory_tid'] = $post['directory_tid4'] ? $post['directory_tid4'] : (
                    $post['directory_tid3'] ? $post['directory_tid3'] : (
                    $post['directory_tid2'] ? $post['directory_tid2'] : (
                    $post['directory_tid1'] ? $post['directory_tid1'] : 0
                    )
                    )
                    );
                    $commonInstance = Common_Model::getInstance();
                    $pathAlias = $commonInstance->callFunction('translate', trim($post['name']));
                    // 检查重复的路径别名, 自动加数字后缀
                    $pathAliases = $this->_productInstance->getProductPathAliasList($pathAlias);
                    if (!empty($pathAliases)) {
                        $n = 1;
                        while(array_search($pathAlias . '-' . $n, $pathAliases)) {
                            ++$n;
                        }
                        $pathAlias .= '-' . $n;
                    }
                    if ($number = $this->_productInstance->getsimilarProductNumber($post['name'])) {
                        $number = str_replace($post['name'], '', $number);
                        $len = strlen($number);
                        $number = intval($number) + 1;
                        $number = str_pad($number, $len, '0', STR_PAD_LEFT);
                    } else {
                        $number = isset($post['num']) ? $post['num'] : '001';
                    }

                    $set = array(
            'directory_tid' => isset($post['directory_tid']) ? $post['directory_tid'] : 0,
            'brand_tid' => isset($post['brand_tid']) ? $post['brand_tid'] : 0,
            'number' => isset($post['sn']) ? $post['sn'].$number : null,
            'sn' => isset($post['sn']) ? $post['sn'].$number : null,
            'name' => isset($post['name']) ? $post['name'].$number : null,
            'sell_price' => isset($post['sell_price']) ? $post['sell_price'] : 0,
            'list_price' => isset($post['list_price']) ? $post['list_price'] : 0,
            'wt' => isset($post['wt']) ? $post['wt'] : 0,
            'stock' => isset($post['stock']) ? $post['stock'] : 0,
            'sell_max' => isset($post['sell_max']) ? $post['sell_max'] : 0,
            'sell_min' => isset($post['sell_min']) ? $post['sell_min'] : 0,
            'summary' => isset($post['summary']) ? $post['summary'] : null,
            'description' => isset($post['description']) ? $post['description'] : null,
            'template' => isset($post['template']) ? $post['template'] : null,
            'type' => isset($post['type']) ? $post['type'] : null,
            'path_alias' => $pathAlias,
            'free_shipping' => isset($post['free_shipping']) && $post['free_shipping'] ? 1 : 0,
            'status' => isset($post['status'])  && $post['status'] ? 1 : 0,
            'shippable' => isset($post['shippable'])  && $post['shippable'] ? 1 : 0,
            'filepath' => url($file->filepath, false),
          );
          $pid = $this->_productInstance->insertProduct($set);
          if ($pid && isset($post['type']) && $post['type']) {
          	$filepost[0]->pid = $pid;
          	$filepost[0]->fid = $file->fid;
          	$filepost[0]->alt = '';
          	$filepost[0]->weight = $weight;
          	$this->_productInstance->updateProductFiles($pid, $filepost);
            $this->_productInstance->insertProductFields($pid, $post['type'], $post);
          }
        }
        /*>>*/
        $filepath = url($file->filepath, false);
        $fileArray = array(
          'fid' => $file->fid,
          'filename' => $file->filename,
          'filepath' => $filepath,
        );

        echo json_encode($fileArray);
      } else {
        echo 0;
      }
    } else {
      echo -1;
    }
  }
  
  public function searchtermsproductsAction() {
  	if ($this->isPost()) {
  		$termsName = trim($_POST['terms_name']);
  		if (empty($termsName)) {
  			setMessage('请输入要查询的terms', 'error');
  		} else {
  			$termInfo = Taxonomy_Model::getInstance()->getTermInfoByName($termsName);
  			if (empty($termInfo)) {
  				setMessage('查询的terms不存在', 'error');
  			} else {
	  			$productInstance = Product_Model::getInstance();
	  			$productSnList = array();
	  			if ($termInfo->vid == Taxonomy_Model::TYPE_DIRECTORY) {
	  				$productSnList = $productInstance->searchProductSnByDir($termInfo->tid);
	  			} else {
	  				$productSnList = $productInstance->searchProductSnByTerm($termInfo->tid);
	  			}
	  			$this->view->assign('productSnList', $productSnList);
  			}
  		}
  	}
  	$this->view->render('admin/product/searchtermsproducts.phtml');
  }
  
  public function ajaxproductdirectoryweightAction($pid, $value){
  	 $vars = $_POST;
  	 $tid = substr($pid, strpos($pid, "dp") + 2);
  	 $pid = substr($pid, 0 , strpos($pid, "dp"));
  	 
  	 $json = array(
  	 		'error' => 1,
  	 		'pid' => $pid,
  	 		'field' => $value,
  	 		'msg' => '修改失败',
  	 );
  	 
  	 $this->_productInstance->updateProduct2($pid, $tid, $value);
  	 $json['error'] = 0;
  	 $json['value'] = $value;
  	 unset($json['msg']);
  	 echo json_encode($json);
  }
  
  public function productdirectoryliststransAction($page = 1, $orderby = 'updated DESC, pid DESC'){
  	if (!access(array('list productdirectory', 'administrator page'), 'or')) {
  		goto403('Access Denied.');
  	}
  	
  	gotoUrl('admin/product/productdirectorylists');
  }
  
  public function productdirectorylistsAction($page = 1, $orderby = 'weight DESC'){
  	if (!access(array('list productdirectory', 'administrator page'), 'or')) {
  		goto403('Access Denied.');
  	}
  	
  	$filter = array();
  	
  	if(! isset($_SESSION['weightmanage_tid1'])){$_SESSION['weightmanage_tid1'] = 0;}
  	if(! isset($_SESSION['weightmanage_tid2'])){$_SESSION['weightmanage_tid2'] = 0;}
  	if(! isset($_SESSION['weightmanage_tid3'])){$_SESSION['weightmanage_tid3'] = 0;}
  	if(! isset($_SESSION['weightmanage_tid4'])){$_SESSION['weightmanage_tid4'] = 0;}
  	
  	$filter['admin_tids'] = 0;
        if ($this->isPost()) {
            $post = $_POST;
            
            $post['tids'] = $post['directory_tid4'] ? $post['directory_tid4'] : (
            $post['directory_tid3'] ? $post['directory_tid3'] : (
            $post['directory_tid2'] ? $post['directory_tid2'] : (
            $post['directory_tid1'] ? $post['directory_tid1'] : 0
            )
            )
            );
            $post['admin_tids'] = $post['tids'];
            $filter = $post;
            foreach ($post as $key=>$dl) {
                $_SESSION['productdirectorylists'][$key] = $dl;
            }
            $page = 1;
            
            $_SESSION['weightmanage_tid1'] = $post['directory_tid1'];
            $_SESSION['weightmanage_tid2'] = $post['directory_tid2'];
            $_SESSION['weightmanage_tid3'] = $post['directory_tid3'];
            $_SESSION['weightmanage_tid4'] = $post['directory_tid4'];

            
            unset($post['directory_tid1']);
            unset($post['directory_tid2']);
            unset($post['directory_tid3']);
            unset($post['directory_tid4']);
        }

        if(isset($_SESSION['productdirectorylists'])){
            $filter = $_SESSION['productdirectorylists'];
        }
        
        $filter['orderby'] = isset($filter['orderby']) ? $filter['orderby'] : $orderby;
        
        $taxonomyInstance = Taxonomy_Model::getInstance();
        if($_SESSION['weightmanage_tid4'] 
        	+ $_SESSION['weightmanage_tid3'] 
        	+ $_SESSION['weightmanage_tid2'] 
        	+ $_SESSION['weightmanage_tid1'] == 0){
        	$productsList = array();
        	$productsCount = 0;
        }
        else{
        	$productsList = $this->_productInstance->getProductsList($filter, $page, 20);
        	$productsCount = $this->_productInstance->getProductsCount($filter);
        	
  /*      	foreach ($productsList as $k => $v){
        		$v->directP = $v->directory_tid4 ? $v->directory_tid4 : (
        				$v->directory_tid3 ? $v->directory_tid3 : (
        						$v->directory_tid2 ? $v->directory_tid2 : (
        								$v->directory_tid1 ? $v->directory_tid1 : 0
        						)
        				)
        		);
        	}
     */
        	
        	$this->_productInstance->addProductsDirectoryWeight($productsList);
        }
        
        
        
        $typeList = $this->_productInstance->getTypeList();
        $brandTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_BRAND, false);
        $brandTreeList = array();
        if (is_array($brandTermsList)) {
            foreach ($brandTermsList as $tid => $term) {
                $brandTreeList[$tid] = $term->name;
            }
        }
        $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
        $directoryTreeList = $taxonomyInstance->getTermsListForHtmlTree($directoryList);
        $directoryTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY, false);
        $this->view->render('admin/product/productdirectorylists.phtml', array(
		      'typeList' => $typeList,
		      'productsCount' => $productsCount,
		      'productsList' => $productsList,
		      'brandList' => $brandTermsList,
		      'brandTreeList' => $brandTreeList,
		      'directoryList' => $directoryList,
		      'directoryTreeList' => $directoryTreeList,
		      'directoryTermsList' => $directoryTermsList,
		      'pagination' => pagination('admin/product/productdirectorylists/%d', $productsCount, 20, $page),
        		'dp1' => $_SESSION['weightmanage_tid1'],
        		'dp2' => $_SESSION['weightmanage_tid2'],
        		'dp3' => $_SESSION['weightmanage_tid3'],
        		'dp4' => $_SESSION['weightmanage_tid4'],
        ));
  }
}
