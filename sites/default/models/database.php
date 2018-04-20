<?php


/**
 * 
 * 创建/备份数据库
 * @author Nemo
 *
 */

class Database_Model extends Bl_Model
{
	var $max_size  = 20971520; // 20M
  var $is_short  = false;
  var $offset    = 300;
  var $dump_sql  = '';
  var $sql_num   = 0;
  var $error_msg = '';
  /**
  * @return Database_Model
  */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }
  
  public function getTables($sql = "SHOW TABLES")
  {
  	global $db;
  	if(isset($sql) && $sql){
 			$result = $db->exec($sql);
 			$temp = $result->all();
 			if($temp){
 				foreach($temp as $k => $v){
 					foreach($v as $table){
 						$tables[$k] = $table;
 					}
 				}
 				return $tables;
 			}
 			return false;
  	}
 		return false;
  }
  
  public function fileExists($base)
  {
	  if (file_exists($base))
    {
       return true;
    }
    return false;
  }
  
  public function createFile($filename,$somecontent=''){
  	if(!$this->fileExists($filename)){
  		if (!$handle = fopen($filename, 'w')) {
          $this->error_msg = "不能打开文件 $filename";
	        return false;
	    }
	    // 将$somecontent写入到我们打开的文件中。
	    if (fwrite($handle, $somecontent) === FALSE) {
	         $this->error_msg = "不能写入到文件 $filename";
	         return false;
	    }
	    fclose($handle);
	    return true;
  	}
  	return false;
  }
  public function make_dir($folder)
  {
  	
  	if(!$this->fileExists($folder)){
  	  /* 如果目录不存在则尝试创建该目录 */
      @umask(0);
      /* 将目录路径拆分成数组 */
      preg_match_all('/([^\/]*)\/?/i', $folder, $atmp);

      /* 如果第一个字符为/则当作物理路径处理 */
      $base = ($atmp[0][0] == '/') ? '/' : '';
      /* 遍历包含路径信息的数组 */
      foreach ($atmp[1] AS $val)
      {
          if ('' != $val)
          {
              $base .= $val;

              if ('..' == $val || '.' == $val)
              {
                  /* 如果目录为.或者..则直接补/继续下一个循环 */
                  $base .= '/';

                  continue;
              }
          }
          else
          {
              continue;
          }

          $base .= '/';

          if (!$this->fileExists($base))
          {
              /* 尝试创建目录，如果创建失败则继续循环 */
              if (@mkdir(rtrim($base, '/'), 0777))
              {
                  @chmod($base, 0777);
              }
          }
      }
  	 }
  }
  
    /**
     *  将文件中数据表列表取出
     *
     * @access  public
     * @param   string      $path    文件路径
     *
     * @return  array       $arr    数据表列表
     */
    function get_tables_list($path)
    {
        if (!file_exists($path))
        {
             $this->error_msg = $path . ' is not exists';

            return false;
        }

        $arr = array();
        $str = @file_get_contents($path);

        if (!empty($str))
        {
            $tmp_arr = explode("\n", $str);
            foreach ($tmp_arr as $val)
            {
                $val = trim ($val, "\r;");
                if (!empty($val))
                {
                    list($table, $count) = explode(':',$val);
                    $arr[$table] = $count;
                }
            }
        }

        return $arr;
    }
  
    /**
     *  将数据表数组写入指定文件
     *
     * @access  public
     * @param   string      $path    文件路径
     * @param   array       $arr    要写入的数据
     *
     * @return  boolen
     */
    public function put_tables_list($path, $arr)
    {
        if (is_array($arr))
        {
            $str = '';
            foreach($arr as $key => $val)
            {
                $str .= $key . ':' . $val . ";\r\n";
            }

            if (@file_put_contents($path, $str))
            {
                return true;
            }
            else
            {
                $this->error_msg = 'Can not write ' . $path;

                return false;
            }
        }
        else
        {
             $this->error_msg = 'It need a array';

            return false;
        }
    }
    
    /**
     *  生成备份文件头部
     *
     * @access  public
     * @param   int     文件卷数
     *
     * @return  string  $str    备份文件头部
     */
    function make_head($vol)
    {
    	  global $db;
        /* 系统信息 */
        $sys_info['web_server'] = $_SERVER['HTTP_HOST'];
        $sys_info['mysql_ver']  = mysql_get_server_info();
        $sys_info['date']       = date('Y-m-d H:i:s');

        $head = "-- boling v2.x SQL Dump Program\r\n".
                 "-- " . $sys_info['web_server'] . "\r\n".
                 "-- \r\n".
                 "-- DATE : ".$sys_info["date"]."\r\n".
                 "-- MYSQL SERVER VERSION : ".$sys_info['mysql_ver']."\r\n".
                 "-- BOLING VERSION : 1.0\r\n".
                 "-- Vol : " . $vol . "\r\n";

        return $head;
    }
    
    /**
     *  备份一个数据表
     *
     * @access  public
     * @param   string      $path       保存路径表名的文件
     * @param   int         $vol        卷标
     *
     * @return  array       $tables     未备份完的表列表
     */
    function dump_table($path, $vol)
    {
        $tables = $this->get_tables_list($path);

        if ($tables === false)
        {
            return false;
        }

        if (empty($tables))
        {
            return $tables;
        }

        $this->dump_sql = $this->make_head($vol);

        foreach($tables as $table => $pos)
        {

            if ($pos == -1)
            {
                /* 获取表定义，如果没有超过限制则保存 */
                $table_df = $this->get_table_df($table, true);
                if (strlen($this->dump_sql) + strlen($table_df) > $this->max_size - 32)
                {
                    if ($this->sql_num == 0)
                    {
                        /* 第一条记录，强制写入 */
                        $this->dump_sql .= $table_df;
                        $this->sql_num +=2;
                        $tables[$table] = 0;
                    }
                    /* 已经达到上限 */

                    break;
                }
                else
                {
                    $this->dump_sql .= $table_df;
                    $this->sql_num +=2;
                    $pos = 0;
                }
            }

            /* 尽可能多获取数据表数据 */
            $post_pos = $this->get_table_data($table, $pos);

            if ($post_pos == -1)
            {
                /* 该表已经完成，清除该表 */
                unset($tables[$table]);
            }
            else
            {
                /* 该表未完成。说明将要到达上限,记录备份数据位置 */
                $tables[$table] = $post_pos;
                break;
            }
        }

        $this->dump_sql .= '-- END boling v2.x SQL Dump Program ';
        $this->put_tables_list($path, $tables);

        return $tables;
    }
    
    /**
     *  获取指定表的定义
     *
     * @access  public
     * @param   string      $table      数据表名
     * @param   boolen      $add_drop   是否加入drop table
     *
     * @return  string      $sql
     */
    function get_table_df($table, $add_drop = false)
    {
    	  global $db;
        if ($add_drop)
        {
            $table_df = "DROP TABLE IF EXISTS `$table`;\r\n";
        }
        else
        {
            $table_df = '';
        }

        $tmp_result = $db->query("SHOW CREATE TABLE `$table`");
       $tmp_arr = $tmp_result->row(false);
         
        $tmp_sql = $tmp_arr['Create Table'];
        $tmp_sql = substr($tmp_sql, 0, strrpos($tmp_sql, ")") + 1); //去除行尾定义。

        $table_df .= $tmp_sql . " ENGINE=MyISAM DEFAULT CHARSET=utf8;\r\n";
        return $table_df;
    }
    
    /**
     *  获取指定表的数据定义
     *
     * @access  public
     * @param   string      $table      表名
     * @param   int         $pos        备份开始位置
     *
     * @return  int         $post_pos   记录位置
     */
    function get_table_data($table, $pos)
    {
    	  global $db;
        $post_pos = $pos;

        /* 获取数据表记录总数 */
        $result = $db->query("SELECT COUNT(*) FROM $table");
				$total = $result->one();
				
        if ($total == 0 || $pos >= $total)
        {
            /* 无须处理 */
            return -1;
        }

        /* 确定循环次数 */
        $cycle_time = ceil(($total-$pos) / $this->offset); //每次取offset条数。需要取的次数

        /* 循环查数据表 */
        for($i = 0; $i<$cycle_time; $i++)
        {
            /* 获取数据库数据 */
            $data = $db->query("SELECT * FROM $table LIMIT " . ($this->offset * $i + $pos) . ', ' . $this->offset)->all(false);
            $data_count = count($data);
            
            $fields = array_keys($data[0]);
            $start_sql = "INSERT INTO `$table` ( `" . implode("`, `", $fields) . "` ) VALUES ";

            /* 循环将数据写入 */
            for($j=0; $j< $data_count; $j++)
            {
                $record = array_map("dump_escape_string", $data[$j]);   //过滤非法字符
                $record = array_map("dump_null_string", $record);     //处理null值
                 
                /* 检查是否能写入，能则写入 */
                if ($this->is_short)
                {
                    if ($post_pos == $total-1)
                    {
                        $tmp_dump_sql = " ( '" . implode("', '" , $record) . "' );\r\n";
                    }
                    else
                    {
                        if ($j == $data_count - 1)
                        {
                            $tmp_dump_sql = " ( '" . implode("', '" , $record) . "' );\r\n";
                        }
                        else
                        {
                            $tmp_dump_sql = " ( '" . implode("', '" , $record) . "' ),\r\n";
                        }
                    }

                    if ($post_pos == $pos)
                    {
                        /* 第一次插入数据 */
                        $tmp_dump_sql = $start_sql . "\r\n" . $tmp_dump_sql;
                    }
                    else
                    {
                        if ($j == 0)
                        {
                            $tmp_dump_sql = $start_sql . "\r\n" . $tmp_dump_sql;
                        }
                    }
                }
                else
                {
                    $tmp_dump_sql = $start_sql . " ('" . implode("', '" , $record) . "');\r\n";
                }

                $tmp_str_pos = strpos($tmp_dump_sql, 'NULL');         //把记录中null值的引号去掉
                $tmp_dump_sql = empty($tmp_str_pos) ? $tmp_dump_sql : substr($tmp_dump_sql, 0, $tmp_str_pos - 1) . 'NULL' . substr($tmp_dump_sql, $tmp_str_pos + 5);

                if (strlen($this->dump_sql) + strlen($tmp_dump_sql) > $this->max_size - 32)
                {
                    if ($this->sql_num == 0)
                    {
                        $this->dump_sql .= $tmp_dump_sql; //当是第一条记录时强制写入
                        $this->sql_num++;
                        $post_pos++;
                        if ($post_pos == $total)
                        {
                            /* 所有数据已经写完 */
                            return -1;
                        }
                    }

                    return $post_pos;
                }
                else
                {
                    $this->dump_sql .= $tmp_dump_sql;
                    $this->sql_num++; //记录sql条数
                    $post_pos++;
                }
            }
        }

        /* 所有数据已经写完 */
        return -1;
    }
    /**
     *  返回错误信息
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function errorMsg()
    {
        return $this->error_msg;
    }
    
    public function getOptimizeTables()
    {
    	global $db;
    	$ret = $db->query("SHOW TABLE STATUS ");
	    $num = 0;
	    $list= array();
	    $rows = $ret->all(false);
	    foreach ($rows as $row)
	    {
	        if (strpos($row['Name'], '_session') !== false)
	        {
	            $res['Msg_text'] = 'Ignore';
	            $row['Data_free'] = 'Ignore';
	        }
	        else
	        {
	            $res = $db->query('CHECK TABLE ' . $row['Name'])->row(false);
	            $num += $row['Data_free'];
	        }
	        $charset = $row['Collation'];
	        $list[0][] = array('table' => $row['Name'], 'type' => $type, 'rec_num' => $row['Rows'], 'rec_size' => sprintf(" %.2f KB", $row['Data_length'] / 1024), 'rec_index' => $row['Index_length'],  'rec_chip' => $row['Data_free'], 'status' => $res['Msg_text'], 'charset' => $charset);
	    }
	    unset($ret);
	    $list[1][] = $num;
	    return $list;
    }
    
    public function run_optimize()
    {
    	global $db;
	    $tables = $db->query("SHOW TABLES")->column();
	    foreach ($tables AS $table)
	    {
	        if ($row = $db->query('OPTIMIZE TABLE ' . $table)->row(false))
	        {
	            /* 优化出错，尝试修复 */
	            if ($row['Msg_type'] =='error' && strpos($row['Msg_text'], 'repair') !== false)
	            {
	                $db->query('REPAIR TABLE ' . $table);
	            }
	        }
	    }
    }
    
   /**
	 * SQL查询
	 */
	public function assign_sql($sql)
	{
	    global $db;
	    $flag = false;
	    $sql = stripslashes($sql);
	
	    /* 解析查询项 */
	    $sql = str_replace("\r", '', $sql);
	    $query_items = explode(";\n", $sql);
	    foreach ($query_items as $key=>$value)
	    {
	        if (empty($value))
	        {
	            unset($query_items[$key]);
	        }
	    }
	    /* 如果是多条语句，拆开来执行 */
	    if (count($query_items) > 1)
	    {
	        foreach ($query_items as $key=>$value)
	        {
	            if ($db->query($value))
	            {
	            	$flag = true;
	            }
	            else
	            {
	                setMessage($db->error(), 'error');
	                $flag = false;
	                return false;
	            }
	        }
	        return $flag; //退出函数
	    }
	
	    /* 单独一条sql语句处理 */
	    if (preg_match("/^(?:UPDATE|DELETE|TRUNCATE|ALTER|DROP|FLUSH|INSERT|REPLACE|SET|CREATE)\\s+/i", $sql))
	    {
	        if ($db->query($sql))
	        {
	            $flag = true;
	        }
	        else
	        {
	            setMessage($db->error(), 'error');
	            $flag = false;
	        }
	        return $flag;
	    }
	    else
	    {
	        $data = $db->query($sql)->all(false);
	        if ($data == false)
	        {
	            setMessage($db->error(), 'error');
	            $flag = false;
	            return false;
	        }
	        else
	        {
	            $result = '';
	            if (is_array($data) && isset($data[0]) === true)
	            {
	                $result = "<table> \n <tr>";
	                $keys = array_keys($data[0]);
	                for ($i = 0, $num = count($keys); $i < $num; $i++)
	                {
	                    $result .= "<th>" . $keys[$i] . "</th>\n";
	                }
	                $result .= "</tr> \n";
	                foreach ($data AS $data1)
	                {
	                    $result .= "<tr>\n";
	                    foreach ($data1 AS $value)
	                    {
	                        $result .= "<td>" . $value . "</td>";
	                    }
	                    $result .= "</tr>\n";
	                }
	                $result .= "</table>\n";
	            }
	            else
	            {
	                $result ="<center><h3>" . $_LANG['no_data'] . "</h3></center>";
	            }
	
	            return $result;
	        }
	    }
	}
  /*
   * end SQL查询
   */
}

/**
 * 对mysql敏感字符串转义
 *
 * @access  public
 * @param   string      $str
 *
 * @return string
 */
function dump_escape_string($str)
{
    return mysql_real_escape_string($str);
}

/**
 * 对mysql记录中的null值进行处理
 *
 * @access  public
 * @param   string      $str
 *
 * @return string
 */
function dump_null_string($str)
{
    if (!isset($str) || is_null($str))
    {
        $str = 'NULL';
    }

    return $str;
}