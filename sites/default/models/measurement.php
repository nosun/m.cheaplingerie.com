<?php
class Measurement_Model extends Bl_Model
{
    /**
     * @return Product_Model
     */
    public static function getInstance()
    {
        return parent::getInstance(__CLASS__);
    }
    
    /**
     * 获取Measurement列表
     * @return array
     */
    public function getMeasurementList()
    {
        global $db;
        static $list;
        if (!isset($list))
        {
            $cacheId = 'measurement-list';
            if ($cacheId = cache::get($cacheId))
            {
                $list = $cache->data;
            }
            else 
            {
                $result = $db->query('select id, name, product_type, content from measurement order by id desc');
                $list = $result->allWithKey('id');
                cache::save($cacheId, $list);
            }
        }
        return $list;
    }

    /**
     * 
     * 新建Measurement
     * @param $measurement 新建的Measurement内容
     */
    public function insertMeasurement($measurement)
    {
        global $db;
        $db->insert('measurement', $measurement);
        $affected = (boolean)$db->affected();
        $cacheId = 'measurement-list';
        if ($cacheId = cache::get($cacheId))
        {
            cache::remove($cacheId);
        }
        return $affected;
    }
    
    /**
     * 
     * 根据id获取Measurement对象
     * @param $id Measurement的id
     */
    public function getMeasurementById($id)
    {
        global $db;
        $result = $db->query("select id, name, product_type, content from measurement where id=" . $id);
        $measurement = $result->row();
        if (false === $measurement)
        {
            return null;
        }
        return $measurement;
    }
    
    /**
     * 
     * 根据type获取Measurement对象
     * @param $type Measurement的product_type
     */
    public function getMeasurementByType($type)
    {
        global $db;
        
        $result = $db->query("select id, name, product_type, content from measurement where product_type =\"" . $type . "\"");
        $sizeChart = $result->row();
        if (false === $sizeChart)
        {
            return null;
        }
        return $sizeChart;
    }
    /**
     * 
     * 更新Measurement.
     * @param $measurement 需要更新的Measurement内容
     */
    public function updateMeasurement($measurement)
    {
        global $db;
        $set = array(
            'name' => $measurement['name'],
            'product_type' => $measurement['product_type'],
            'content' => $measurement['content'],);
        $where = array(
            'id' => $measurement['id'],);
        $result = $db->update('measurement', $set, $where);
        $affected = (boolean)$db->affected();
        return $affected;
    }
    
    public function deleteSizeChart($id)
    {
        global $db;
        $where = array(
            'id' => $id,);
        $result = $db->delete('measurement', $where);
        $affected = (boolean)$db->affected();
        return $affected;
    }
}