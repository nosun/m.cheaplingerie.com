<?php
class TypeFilterField_Model extends Bl_Model
{
    /*商品的过滤选项类别
     * 1. 基本属性
     * 2. 扩展属性
     * 3. tag
     * 4. recommened
     * 5. brand
     */
    const ATTR_TYPE_SELF = 1;
    const ATTR_TYPE_MULTI = 2;
    const ATTR_TYPE_TAG = 3;
    const ATTR_TYPE_RECOMMENED = 4;
    const ATRR_TYPE_BRAND = 5;
    
    const ATTR_VALUE_TYPE_SCALAR = 1;
    const ATTR_VALUE_TYPE_ENUM = 2;
    
    /**
     * @return ProductFilterAttr_Model
     */
    public static function getInstance()
    {
        return parent::getInstance(__CLASS__);
    }
    
    public static function attrTypeToTaxonomyType($attrType)
    {
        switch ($attrType) {
            case TypeFilterField_Model::ATTR_TYPE_TAG:
                return Taxonomy_Model::TYPE_TAG;
            case TypeFilterField_Model::ATTR_TYPE_RECOMMENED:
                return Taxonomy_Model::TYPE_RECOMMEND;
            case TypeFilterField_Model::ATRR_TYPE_BRAND:
                return Taxonomy_Model::TYPE_BRAND;
            default:
                return 0;
        }
    }
    /**
     * 添加商品类别过滤属性
     * @param obj $typeFilterField
     */
    public function addTypeFilterField($typeFilterField)
    {
    	global $db;
    	$db->insert('type_filter_field', $typeFilterField);
    	return $db->affected() == 1;
    }
    
    /**
     * 修改商品过滤属性
     * @param $typeFilterField
     */
    public function editTypeFilterField($typeFilterField)
    {
        global $db;
        $db->update('type_filter_field', $typeFilterField, array('id' => $typeFilterField->id));
        return $db->affected() == 1;
    }
    
    /**
     * 删除商品过滤属性
     * @param unknown_type $id
     */
    public function deleteTypeFilterField($id, $type=null)
    {
        global $db;
        $where = array('id' => $id);
        if (isset($type))
        {
            $where['product_type'] = $type;
        }
        $db->delete('type_filter_field', $where);
        return $db->affected() == 1;   
    }
    
    /**
     * 获取过滤选项列表
     * @param $productType 商品类别
     */
    public function getTypeFilterFieldByType($productType=null)
    {
        global $db;
        if (!empty($productType))
        {
        	$db->where('product_type', $productType);
        }
        $db->orderby('id');
        $result = $db->get('type_filter_field');
        return $result->all();
    }
    
    
    /**
     * 根据过滤选项组号获取过滤选项列表
     * @param $groupId 过滤选项组编号
     */
    public function getTypeFilterFieldByGroupId($groupId)
    {
        global $db;
        if (!empty($groupId))
        {
            $db->where('group_id', $groupId);
        }
        $db->orderby('id');
        $result = $db->get('type_filter_field');
        return $result->all();
    }
    
    /**
     * 根据termInfo获取过滤属性
     * 原则：获取term下面数量最大的产品分类
     * @param $termInfo
     */
    /*
    public function getTypeFilterFieldByTermInfo($termInfo)
    {
        global $db;
        switch ($termInfo->vid)
        {
            case Vocabulary_Model::BRAND:
                $sql = sprintf('select count(p.type) as c , p.type from products p where brand_tid="%d" group by p.type order by c desc limit 1', $termInfo->tid);
                break;
            case Vocabulary_Model::DIRECTORY:
                if (!$termInfo->ptid1)
                {
	                $directoryName = "directory_tid1";
	            }
	            else if (!$termInfo->ptid2)
	            {
	                $directoryName = "directory_tid2";
	            }
	            else if (!$termInfo->ptid3)
	            {
	                $directoryName = "directory_tid3";
	            }
	            else
	            {
	                $directoryName = "directory_tid4";
	            }
	            if (isset($directoryName))
	            {
	                $sql = sprintf('select count(p.type) as c , p.type from products p where %s="%d" group by p.type order by c desc limit 1', $directoryName, $termInfo->tid);
	            }
                break;
            default:
                $sql = sprintf('select count(p.type) as c , p.type from terms_products t, products p where t.tid="%d" and t.pid = p.pid group by p.type order by c desc limit 1', $termInfo->tid);
                break;
        }
        $filterFieldList = null;
        if (isset($sql))
        {
            $result = $db->query($sql);
            $productType = $result->one(1);
            $filterFieldList = $this->getTypeFilterFieldByType($productType);
        }
        return $filterFieldList;
    }
    */
    
    /**
     * 根据termInfo获取过滤属性
     * 原则：获取term下面数量最大的产品分类
     * @param $termInfo
     */
    public function getTypeFilterFieldByTermInfo($termInfo)
    {
        $filterFieldList = null;
        if (isset($termInfo->tid))
        {
            $filterGroup = Filtergroup_Model::getInstance()->getTermsFilterGroup($termInfo->tid);
            if (!empty($filterGroup) && $filterGroup->fid != 0) {
                $filterFieldList = $this->getTypeFilterFieldByGroupId($filterGroup->fid);
            }
        }
        return $filterFieldList;
    }
    /**
     * 根据id获取过滤属性
     * @param $id
     * @param $productType 商品的类别
     */
    public function getTypeFilterFieldById($id, $productType=null)
    {
        global $db;
        $db->where('id', $id);
        if (isset($productType))
        {
            $db->where('product_type', $productType);
        }
        $result = $db->get('type_filter_field');
        return $result->row();
    }
    
    /**
     * 根据条件数组获取过滤属性
     * @param $whereArray
     */
    public function getTypeFilterFieldByArray($whereArray)
    {
        global $db;
        foreach ($whereArray as $key => $value)
        {
            $db->where($key, $value);
        }
        $db->orderby('id');
        $result = $db->get('type_filter_field');
        return $result->all();
    }
}