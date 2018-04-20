<?php
class Admin_Filtergroup_Controller extends Bl_Controller
{
    public static function __permissions()
    {
        return array(
        'list filtergroup',
        'edit filtergroup',
        'delete filtergroup',
        );
    }
  
    public function init()
    {
    }
    
    public function getListAction()
    {
        $filterGroupList = Filtergroup_Model::getInstance()->getFilterGroupList();
        $this->view->render('admin/filtergroup/list.phtml', array(
            'filterGroupList' => $filterGroupList,
        ));
    }
    
    public function insertAction()
    {
        if (!access('edit filtergroup'))
        {
            goto403('Access Denied.');
        }
        $filterGroupInstance = Filtergroup_Model::getInstance();
        if ($this->isPost())
        {
            $post = $_POST;
            $filterGroupName = $post['filter_group_name'];
            $ret = $filterGroupInstance->getFilterGroupByName($filterGroupName);
            if (isset($ret->id))
            {
                setMessage('过滤选项组:' . $filterGroupName . '已经存在', 'error');
            }
            else
            {
                $groupId = $filterGroupInstance->insertFilterGroup($filterGroupName);
                if ($groupId != -1)
                {
                    setMessage('创建过滤选项组：' . $filterGroupName . '成功', 'notice');
                    gotoUrl('admin/filtergroup/fieldlist/' . $groupId);
                }
                else 
                {
                    setMessage('创建过滤选项组：' . $filterGroupName . '失败', 'error');
                }
            }
        }
        $this->view->render('admin/filtergroup/insertfiltergroup.phtml');
    }
    
    public function editAction($groupId)
    {
        if (!access('edit filtergroup'))
        {
            goto403('Access Denied.');
        }
        $filterGroup = Filtergroup_Model::getInstance()->getFilterGroupById($groupId);
        if (!isset($filterGroup->id))
        {
            setMessage('选项组不存在', 'error');
            gotoUrl('admin/filtergroup/getlist');
        }
        if ($this->isPost())
        {
            $post = $_POST;
            if (Filtergroup_Model::getInstance()->updateFilterGroup($groupId, $post['filter_group_name']))
            {
                setMessage('更新选项组成功', 'notice');
                gotoUrl('admin/filtergroup/getlist');
            }
            else 
            {
                setMessage('更新选项组失败-选项未做修改', 'error');
            }
        }
        $this->view->render('admin/filtergroup/editfiltergroup.phtml', array(
                            'filterGroup' => $filterGroup
        ));
    }
    
    public function deleteAction($groupId)
    {
        if (!access('delete filtergroup'))
        {
            goto403('Access Denied.');
        }
        $filterGroup = Filtergroup_Model::getInstance()->getFilterGroupById($groupId);
        if (!isset($filterGroup->id))
        {
            setMessage('选项组不存在', 'error');
            gotoUrl('admin/filtergroup/getlist');
        }
        if (Filtergroup_Model::getInstance()->deleteFilterGroup($groupId))
        {
            setMessage('删除选项组成功', 'notice');
            gotoUrl('admin/filtergroup/getlist');
        }
        else
        {
            setMessage('删除选项组失败', 'error');
        }
    }
    
    public function fieldlistAction($groupId)
    {
        if (!access('edit filtergroup'))
        {
            goto403('Access Denied.');
        }
        $filterGroup = Filtergroup_Model::getInstance()->getFilterGroupById($groupId);
        $filterGroup->filter_fields = TypeFilterField_Model::getInstance()->getTypeFilterFieldByGroupId($groupId);
        $this->view->render('admin/filtergroup/filterfieldlist.phtml', array(
                            'filterGroup' => $filterGroup
        ));
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
    
    public function checkFormPara($post, &$typeFilterField, $isEdit=false)
    {
        $groupId = intval($post['filter_group_id']);
        if ($groupId <= 0)
        {
            setMessage('过滤选项组id丢失', "error");
            return false;
        }
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
        $typeFilterField->group_id = $groupId;
        $typeFilterField->name = trim($post['name']);
        if (!$isEdit)
        {
            $typeFilterField->product_type = $post['product_type'] == 'empty' ? '' : $post['product_type'];
        }
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
    
    public function addfilterfieldAction($groupId)
    {
        if (!access('edit filtergroup'))
        {
            goto403('Access Denied.');
        }
        if ($this->isPost())
        {
            if ($this->checkFormPara($_POST, $typeFilterField))
            {
                if (!TypeFilterField_Model::getInstance()->addTypeFilterField($typeFilterField))
                {
                    setMessage('添加过滤选项失败', 'error');
                }
                else
                {
                    setMessage('添加过滤选项成功', 'notice');
                    gotoUrl('admin/filtergroup/fieldlist/' . $groupId);
                }
            }
        }
        $typeList = Product_Model::getInstance()->getTypeList();
        $this->view->assign("typeList", $typeList);
        $filterFieldList = $this->getFilterFieldList(1, null);
        $this->view->render("admin/filtergroup/filterfieldnew.phtml", array(
        'filterFieldList' => $filterFieldList,
        'groupId' => $groupId,
        ));
    }
    
    public function editfilterfieldAction($id)
    {
        $typeFilterField = TypeFilterField_Model::getInstance()->getTypeFilterFieldById($id, null);
        if (!isset($typeFilterField))
        {
            setMessage('未找到指定的过滤选项', 'error');
            gotoUrl('admin/filtergroup/getlist/');
        }
        if ($this->isPost())
        {
            if ($this->checkFormPara($_POST, $updatedTypeFilterField, true))
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
                        gotoUrl('admin/filtergroup/fieldlist/' . $typeFilterField->group_id);
                    }
                }
            }
        }
        $typeList = Product_Model::getInstance()->getTypeList();
        $this->view->assign("typeList", $typeList);
        $type = $typeFilterField->product_type;
        $typeFilterField->values = unserialize($typeFilterField->values);
        $filterNameFieldList = $this->getFilterFieldList($typeFilterField->attr_type, $type);
        $filterValueList = $this->getFilterFieldAttrValueList($typeFilterField->attr_type, $type, $typeFilterField->attr_name);
        $this->view->assign('groupId', $typeFilterField->group_id);
        $this->view->assign('type', $type);
        $this->view->assign('field', $typeFilterField);
        $this->view->assign('filterFieldList', $filterNameFieldList);
        $this->view->assign('filterValueList', $filterValueList);
        $this->view->render('admin/filtergroup/filterfieldedit.phtml');
    }
    
    public function deletefilterfieldAction($id)
    {
        $typeFilterField = TypeFilterField_Model::getInstance()->getTypeFilterFieldById($id, null);
        if (!isset($typeFilterField))
        {
            setMessage('未找到指定的过滤选项', 'error');
            gotoUrl('admin/filtergroup/getlist/');
        }
        if (TypeFilterField_Model::getInstance()->deleteTypeFilterField($id, null))
        {
            setMessage('过滤选项删除成功', 'notice');
        }
        else
        {
            setMessage('过滤选项删除失败', 'error');
        }
        gotoUrl('admin/filtergroup/fieldlist/' . $typeFilterField->group_id);
    }
    
}