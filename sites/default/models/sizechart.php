<?php
class SizeChart_Model extends Bl_Model
{
	/**
	 * @return Product_Model
	 */
	public static function getInstance()
	{
		return parent::getInstance(__CLASS__);
	}
	
	/**
	 * 获取Size Chart列表
	 * @return array
	 */
	public function getSizeChartList()
	{
		global $db;
		static $list;
		if (!isset($list))
		{
			$cacheId = 'size_chart-list';
			if ($cacheId = cache::get($cacheId))
			{
				$list = $cache->data;
			}
			else 
			{
			    $db->select('size_chart.*, terms.name_cn as brand_name');
			    $db->join('terms', 'size_chart.brand_tid = terms.tid');
				$result = $db->get('size_chart');
				$list = $result->allWithKey('id');
				cache::save($cacheId, $list);
			}
		}
		return $list;
	}

	/**
	 * 
	 * 新建Size Chart
	 * @param $sizeChart 新建的SizeChart内容
	 */
    public function insertSizeChart($sizeChart)
    {
        global $db;
        $db->insert('size_chart', $sizeChart);
        $affected = (boolean)$db->affected();
        $cacheId = 'size_chart-list';
		if ($cacheId = cache::get($cacheId))
		{
		    cache::remove($cacheId);
		}
        return $affected;
    }
    
    /**
     * 
     * 根据id获取size chart对象
     * @param $id size chart的id
     */
    public function getSizeChartById($id)
    {
        global $db;
        $db->where('id', $id);
        $result = $db->get('size_chart');
        $sizeChart = $result->row();
        if (false === $sizeChart)
        {
            return null;
        }
        return $sizeChart;
    }
    
	/**
     * 
     * 根据type获取size chart对象
     * @param $type size chart的product_type
     */
    public function getSizeChartByBrand($brand_tid)
    {
        global $db;
        $db->where('brand_tid', $brand_tid);
        $result = $db->get('size_chart');
        $sizeChart = $result->row();
        if (false === $sizeChart)
        {
            return null;
        }
        return $sizeChart;
    }
    /**
     * 
     * 更新size chart.
     * @param $sizeChart 需要更新的size chart内容
     */
    public function updateSizeChart($sizeChart)
    {
        global $db;
        $set = array(
            'name' => $sizeChart['name'],
            'brand_tid' => $sizeChart['brand_tid'],
            'content' => $sizeChart['content'],);
        $where = array(
            'id' => $sizeChart['id'],);
        $result = $db->update('size_chart', $set, $where);
        $affected = (boolean)$db->affected();
        return $affected;
    }
    
    public function deleteSizeChart($id)
    {
        global $db;
        $where = array('id' => $id,);
        $result = $db->delete('size_chart', $where);
        $affected = (boolean)$db->affected();
        return $affected;
    }
}