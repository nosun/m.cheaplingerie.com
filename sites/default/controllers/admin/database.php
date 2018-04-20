<?php
class Admin_Database_Controller extends Bl_Controller
{
	private $_dbInstance;
  public static function __permissions()
  {
    return array(
      'manage database',
    );
  }
  
  public function init()
  {
    if (!access('manage database')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_dbInstance = Database_Model::getInstance();
  }
  
  public function indexAction()
  {
    $this->backupAction();
  }
  
  public function backupAction()
  {
  	$list = array();
  	if($this->isPost()){
  		/* 设置最长执行时间为5分钟 */
      @set_time_limit(300);
      
	  	/* 初始化输入变量 */
	    if (empty($_POST['backupname']))
	    {
	        $sql_file_name = date('YmdHis');
	    }
	    else
	    {
	        $sql_file_name = str_replace("0xa", '', trim($_POST['backupname'])); // 过滤 0xa 非法字符
	        $pos = strpos($sql_file_name, '.sql');
	        if ($pos !== false)
	        {
	            $sql_file_name = substr($sql_file_name, 0, $pos);
	        }
	    }
    
  		
  		$temp = $this->_dbInstance->getTables();
  		$vol = empty($_REQUEST['vol']) ? 1 : intval($_REQUEST['vol']);
  		
  		$sqldata = strtolower($_SERVER['HTTP_HOST']);
  		$path = DOCROOT . '/data/' . $sqldata;
  		$run_log = DOCROOT . '/data/' . 'run.log';
  		$this->_dbInstance->make_dir($path);
  		$this->_dbInstance->createFile($run_log);
  		foreach ($temp AS $table)
      {
        $tables[$table] = -1;
      }
    	$this->_dbInstance->put_tables_list($run_log, $tables);
    	/* 开始备份 */
      $tables = $this->_dbInstance->dump_table($run_log, $vol);
	  	if ($tables === false)
	    {
	        setMessage($this->_dbInstance->errorMsg());
	    }
	
	    if (empty($tables))
	    {
	        /* 备份结束 */
	        if ($vol > 1)
	        {
	            /* 有多个文件 */
	            @file_put_contents($path . '/' . $sql_file_name . '_' . $vol . '.sql', $this->_dbInstance->dump_sql);
	            for ($i = 1; $i <= $vol; $i++)
	            {
	                $list[] = array('name' => $sql_file_name . '_' . $i . '.sql', 'url' => 'data/' . $sqldata . '/' . $sql_file_name . '_' . $i . '.sql');
	            }
	        }
	        else
	        {
	            /* 只有一个文件 */
	            @file_put_contents($path . '/' . $sql_file_name . '.sql', $this->_dbInstance->dump_sql);
	            $list[] = array('name' => $sql_file_name . '.sql', 'url' => 'data/' . $sqldata . '/' . $sql_file_name . '.sql');
	        }
	    }
	    else
	    {
	        /* 下一个页面处理 */
	        @file_put_contents($path . '/' . $sql_file_name . '_' . $vol . '.sql', $this->_dbInstance->dump_sql);
	    }
  	}
  	$this->view->render('admin/database/backup.phtml',array(
  	'list' => $list,
  	));
  }

  public function restoreAction()
  {
  	if($this->isPost()){
  		 $sqldata = strtolower($_SERVER['HTTP_HOST']);
  		 $path = DOCROOT . '/data/' . $sqldata;
  		 $sql_file = $path. '/upload_database_bak.sql';
  		  if (empty($_FILES['sqlfile']))
        {
            setMessage('请上传数据库文件！', 'error');
            gotoBack();
        }

        $file = $_FILES['sqlfile'];

        /* 检查上传是否成功 */
        if ((isset($file['error']) && $file['error'] > 0) || (!isset($file['error']) && $file['tmp_name'] =='none'))
        {
            setMessage('文件上传失败！', 'error');
            gotoBack();
        }

        /* 检查文件格式 */
        if ($file['type'] == 'application/x-zip-compressed')
        {
            setMessage('上传文件格式不正确！', 'error');
            gotoBack();
        }

        if (!preg_match("/\.sql$/i" , $file['name']))
        {
            setMessage('请上传.sql文件！', 'error');
            gotoBack();
        }

        /* 将文件移动到临时目录，避免权限问题 */
        @unlink($sql_file);
        if (!move_upload_file($file['tmp_name'] , $sql_file ))
        {
            setMessage('移到临时目录失败！', 'error');
            gotoBack();
        }
		  	/* 设置最长执行时间为5分钟 */
		    @set_time_limit(300);
		
		    if ($this->sql_import($sql_file))
		    {
		        @unlink($sql_file);
		        setMessage('数据库恢复成功！');
		    }
		    else
		    {
		        @unlink($sql_file);
		        setMessage('恢复失败！', 'error');
		    }
		    gotoBack();
  	}
  	
  	$this->view->render('admin/database/restore.phtml');
  }
  
	function sql_import($sql_file)
	{

    $sql_str = array_filter(file($sql_file), 'remove_comment');
    $sql_str = str_replace("\r", '', implode('', $sql_str));

    $ret = explode(";\n", $sql_str);
    $ret_count = count($ret);

    /* 执行sql语句 */
    for($i = 0; $i < $ret_count; $i++)
    {
      $ret[$i] = trim($ret[$i], " \r\n;"); //剔除多余信息
      if (!empty($ret[$i]))
      {
        if ((strpos($ret[$i], 'CREATE TABLE') !== false) && (strpos($ret[$i], 'DEFAULT CHARSET=utf8')=== false))
        {
            /* 建表时缺 DEFAULT CHARSET=utf8 */
             $ret[$i] = $ret[$i] . 'DEFAULT CHARSET=utf8';
         }
          $GLOBALS['db']->query($ret[$i]);
       }
    }
   

    return true;
	}
	
	public function optimizeAction()
	{
		if($this->isPost()){
			$this->_dbInstance->run_optimize();
			setMessage('优化操作已成功，共清理碎片：' . $_POST['num']);
			gotoBack();
		}
		$list = $this->_dbInstance->getOptimizeTables();
		$this->view->render('admin/database/optimize.phtml',array(
		'list' => $list,
		));
	}
	
  public function mainAction()
  {
  	$result = false;
  	$sql = "";
    if($this->isPost()){
    	if(isset($_POST['sql']) && $_POST['sql']){
    		$sql = $_POST['sql'];
				$result = $this->_dbInstance->assign_sql($sql);
				if($result){
					setMessage('执行成功');
				}else{
					setMessage('执行失败');
				}
				
	    }else{
	    	setMessage('SQL语句为空', 'error');
	    }
	    //gotoBack();
		}
  	$this->view->render('admin/database/sql.phtml',
  	array(
  	'result' => $result,
  	'sql' => $sql,
  	));
  }
  
  public function iframeAction()
  {
  	$this->view->render('admin/database/sqlifr.phtml');
  }
}

	
	/**
	 *
	 *
	 * @access  public
	 * @param
	 * @return  void
	 */
	function remove_comment($var)
	{
	  return (substr($var, 0, 2) != '--');
	}
	
	
/**
 * 将上传文件转移到指定位置
 *
 * @param string $file_name
 * @param string $target_name
 * @return blog
 */
	function move_upload_file($file_name, $target_name = '')
	{
	    if (function_exists("move_uploaded_file"))
	    {
	        if (move_uploaded_file($file_name, $target_name))
	        {
	            @chmod($target_name,0755);
	            return true;
	        }
	        else if (copy($file_name, $target_name))
	        {
	            @chmod($target_name,0755);
	            return true;
	        }
	    }
	    elseif (copy($file_name, $target_name))
	    {
	        @chmod($target_name,0755);
	        return true;
	    }
	    return false;
	}