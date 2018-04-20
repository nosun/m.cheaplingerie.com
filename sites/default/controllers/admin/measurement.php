<?php
class Admin_Measurement_Controller extends Bl_Controller
{
    public static function __permissions()
    {
        return array(
            'list measurement',
            'add measurement',
            'update measurement',
            'delete measurement',
        );
    }
    
    public function checkMeasurement($measurement)
    {
        if (!isset($measurement))
        {
            setMessage('服务器接收数据错误', 'error');
            return false;
        }
        if (!isset($measurement['name']) || strlen(trim($measurement['name'])) <= 0)
        {
            setMessage('Measurement的名字不能为空', 'error');
            return false;
        }
        if (!isset($measurement['product_type']) || strlen(trim($measurement['product_type'])) <= 0)
        {
            setMessage('Measurement的产品类别不能为空', 'error');
            return false;
        }
        if (!isset($measurement['content']) || strlen(trim($measurement['content'])) <= 0)
        {
            setMessage('Measurement的内容不能为空', 'error');
            return false;
        }
        return true;
    }
    
    public function listAction()
    {
        if (!access('list measurement'))
        {
            goto403('Access Denied.');  
        }
        $measurementList = Measurement_Model::getInstance()->getMeasurementList();
        $this->view->render('admin/measurement/list.phtml', array(
                            'measurementList' => $measurementList));
    }
    
    public function addAction()
    {
        if (!access('add measurement'))
        {
            goto403('Access Denied.');
        }
        
        if ($this->isPost())
        {
            $measurement = $_POST;
            if ($this->checkMeasurement($measurement))
            {
                if (Measurement_Model::getInstance()->insertMeasurement($measurement))
                {
                    setMessage("新建Measurement:" . $measurement['name'] . '成功', 'notice');
                    gotoUrl("admin/measurement/list");
                } 
                else 
                {
                    setMessage("新建Measurement失败", 'error');
                }
            }
        }
        $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
        $this->view->addJs(url('scripts/swfupload-jquery/vendor/swfupload/swfupload.js'));
        $this->view->addJs(url('scripts/swfupload-jquery/src/jquery.swfupload.js'));
        $this->view->addCss(url('scripts/swfupload-jquery/css/default.css'));
        $productTypeList = Product_Model::getInstance()->getTypeList();
        $this->view->render('admin/measurement/add.phtml', array(
                            'productTypeList' => $productTypeList));
    }

    public function updateAction($id = 0)
    {
        if (!access('update measurement'))
        {
            goto403('Access Denied.');
        }
        
        $measurement = Measurement_Model::getInstance()->getMeasurementById($id);
        if (isset($measurement))
        {
            if ($this->isPost())
            {
                $updateMeasurement = $_POST;
                if (isset ($updateMeasurement) && isset($updateMeasurement['id']))
                {
                    // 判断页面的url中包含的id和真实的id不一致的情况
                    if ($id != $updateMeasurement['id'])
                    {
                        setMessage('页面数据错误', 'error');
                    }
                    else if ($measurement->name == $updateMeasurement['name'] && 
                        $measurement->product_type == $updateMeasurement['product_type'] &&
                        $measurement->content == $updateMeasurement['content'])
                    {
                        setMessage('页面数据未改动', 'error');
                    }
                    else 
                    {
                        if ($this->checkMeasurement($updateMeasurement))
                        {
                            if (Measurement_Model::getInstance()->updateMeasurement($updateMeasurement))
                            {
                                setMessage("更新Measurement:" . $updateMeasurement['name'] . '成功', 'notice');
                                gotoUrl('admin/measurement/list');
                            }
                            else 
                            {
                                setMessage('更新Measurement失败', 'error');
                            }
                        }
                    }
                }
                else 
                {
                    setMessage('输入数据错误', 'error');
                }
            }
            $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
            $this->view->addJs(url('scripts/swfupload-jquery/vendor/swfupload/swfupload.js'));
            $this->view->addJs(url('scripts/swfupload-jquery/src/jquery.swfupload.js'));
            $this->view->addCss(url('scripts/swfupload-jquery/css/default.css'));
            $productTypeList = Product_Model::getInstance()->getTypeList();
            $this->view->render('admin/measurement/update.phtml', array(
                                'productTypeList' => $productTypeList,
                                'measurement' => $measurement));
        }
        else 
        {
            gotoUrl('admin/measurement/list');
        }
    }

    public function deleteAction($id = 0)
    {
        if (!access('delete measurement'))
        {
            goto403('Access Denied.');
        }
        $measurement = Measurement_Model::getInstance()->getMeasurementById($id);
        if (!isset($measurement))
        {
            setMessage('没找到指定的Measurement', 'error');
        }
        else
        {
            if (Measurement_Model::getInstance()->deleteMeasurement($id))
            {
                setMessage('删除Measurement:' . $measurement->name . ' 成功', 'notice');
            }
            else 
            {
                setMessage('删除Measurement:' . $measurement->name . ' 失败', 'error');
            }
        }
        gotoUrl('admin/measurement/list');
    }
}