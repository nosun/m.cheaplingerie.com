<?php
class Filtergroup_Model extends Bl_Model
{
    public static function getInstance()
    {
        return parent::getInstance(__CLASS__);
    }
    
    public function getFilterGroupList()
    {
        global $db;
        $sql = "select * from filter_group order by id desc";
        $result = $db->query($sql);
        return $result->allWithKey('id');
    }
    
    public function getFilterGroupById($id)
    {
        global $db;
        $sql = sprintf("select * from filter_group where id='%s'", trim($id));
        $result = $db->query($sql);
        return (object)$result->row();   
    }
    
    public function getFilterGroupByName($name)
    {
        global $db;
        $sql = sprintf("select id from filter_group where name='%s'", trim($name));
        $result = $db->query($sql);
        return (object)$result->row();   
    }
    
    public function insertFilterGroup($name)
    {
        global $db;
        $sql = sprintf("insert into filter_group(name) values('%s')", trim($name));
        $db->exec($sql);
        if ($db->affected() > 0)
        {
            return $db->lastInsertId();
        }
        else 
        {
            return -1;    
        }
    }
    
    public function updateFilterGroup($id, $name)
    {
        global $db;
        $db->update('filter_group', array('name' => $name), array('id' => $id));
        return $db->affected() > 0;
    }
    
    public function deleteFilterGroup($id)
    {
        global $db;
        $db->delete('filter_group', array('id' => $id));
        return $db->affected() > 0;
    }
    
    /**
     * 更新terms和filter group之间的关系
     * @param $tid  terms id
     * @param $fid  filter group id
     */
    public function updateTermsFilterGroup($tid, $fid)
    {
        global $db;
        $sql = sprintf('update terms_filter_group set fid=%d where tid=%d', $fid, $tid);
        $db->exec($sql);
        return $db->affected() > 0;
    }
    
    public function insertTermsFilterGroup($tid, $fid)
    {
        global $db;
        $sql = sprintf('insert into terms_filter_group(tid, fid) values(%d,%d)', $tid, $fid);
        $db->exec($sql);
        return $db->affected() > 0;
    }
    
    public function getTermsFilterGroup($tid)
    {
        global $db;
        $sql = sprintf('select * from terms_filter_group where tid=%d', $tid);
        $result = $db->query($sql);
        return $result->row();
    }
}