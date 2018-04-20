<?php
class ProductFilterAttr_Model extends Bl_Model
{
    /**
     * @return ProductFilterAttr_Model
     */
	public static function getInstance()
	{
		return parent::getInstance(__CLASS__);
	}
	
	/**
	 * @abstract 获取产品自身可以用于过滤的属性集合
	 */
	public function getProductFilterAttrList()
	{
		global $db;
		$cacheId = 'product_filter_attr_list';
		if ($cache = cache::get($cacheId))
		{
			$productFilterList = $cache->data;
		}
		else
		{
		  $result = $db->get('product_filter_attr');
		  $productFilterList = $result->all();
		  cache::save($cacheId, $productFilterList);
		}
		return $productFilterList;
	}
}