<?php
final class Bl_Database
{
  private $_queries = array();
  private $_conn = null;
  private $_activeRec;
  private $_conf = null;

  public function __construct()
  {
    $this->_resetSelect();
  }

  private function _resetSelect()
  {
    $this->_activeRec = array(
      'SELECT'   => array(),
      'DISTINCT' => false,
      'FROM'     => array(),
      'WHERE'    => array(),
      'JOIN'     => array(),
      'ORDERBY'  => array(),
      'GROUPBY'  => array(),
      'HAVING'   => array(),
      'LIMIT'    => null,
      'OFFSET'   => null,
    );
  }

  public function connect($host = 'localhost', $user = null, $passwd = null, $name = null, $delayed = true)
  {
    if (is_array($host)) {
      $user = $host['user'];
      $passwd = $host['passwd'];
      $name = $host['name'];
      if (isset($host['delayed'])) {
        $delayed = $host['delayed'];
      }
      $host = $host['host'];
    }
    if (!isset($user) || !isset($passwd) || !isset($name)) {
      return;
    }
    $this->_conf = array(
      'host' => $host,
      'user' => $user,
      'passwd' => $passwd,
      'name' => $name,
    );
    if (!$delayed) {
      $this->_connect();
    }
  }

  private function _connect()
  {
    if (false === ($this->_conn = mysql_connect($this->_conf['host'], $this->_conf['user'], $this->_conf['passwd']))) {
      throw new Bl_Db_Exception($this->error(), $this->errno());
    }
    $this->dbSelect($this->_conf['name']);
    $this->exec('SET NAMES "UTF8"');
//    $this->exec('SET SQL_MODE = ""');
    //unset($this->_conf);
  }

  public function disconnect()
  {
    if ($this->_conn) {
      mysql_close($this->_conn);
      //unset($this->_conn);
      $this->_conn = null;
    }
  }

  public function actived()
  {
    return (bool)$this->_conn;
  }

  public function dbSelect($name)
  {
    if (!mysql_select_db($name, $this->_conn)) {
      $error = $this->error();
      $errno = $this->errno();
      $this->disconnect();
      throw new Bl_Db_Exception($error, $errno);
    }
  }

  public function escape($text)
  {
    if (!$this->_conn) {
      $this->_connect();
    }
    return mysql_real_escape_string($text, $this->_conn);
  }

  public function query($sql)
  {
    if (!$this->_conn) {
      $this->_connect();
    }
    $this->_queries[] = $sql;
    $result = mysql_query($sql, $this->_conn);
    if (!$result) {
      throw new Bl_Db_Exception($this->error(), $this->errno(), $sql);
    } else {
      return new Bl_Db_Result($result);
    }
  }

  public function exec($sql)
  {
    if (!$this->_conn) {
      $this->_connect();
    }
    $this->_queries[] = $sql;
    $result = mysql_unbuffered_query($sql, $this->_conn);
    if (!$result) {
      throw new Bl_Db_Exception($this->error(), $this->errno(), $sql);
    } else {
      return new Bl_Db_Result($result);
    }
  }

  public function error()
  {
    if ($this->_conn) {
      return mysql_error($this->_conn);
    } else {
      return mysql_error();
    }
  }

  public function errno()
  {
    if ($this->_conn) {
      return mysql_errno($this->_conn);
    } else {
      return mysql_errno();
    }
  }

  public function getQueries()
  {
    return $this->_queries;
  }
  
	public function getLastsql()
  {
    return end($this->_queries);
  }

  public function lastInsertId()
  {
    return mysql_insert_id($this->_conn);
  }

  public function affected()
  {
    return mysql_affected_rows($this->_conn);
  }

  public function insert($table, $set, $ignore = false)
  {
    $keys = array();
    $values = array();
    foreach ($set as $key => $value) {
      $keys[] = '`' . $this->escape($key) . '`';
      $field = true;
      if (is_array($value) && isset($value['value'])) {
        if (isset($value['escape']) && false === $value['escape']) {
          $field = false;
        }
        $value = $value['value'];
      }
      $value = $this->escape($value);
      if ($field) {
        $value = '"' . $value . '"';
      }
      $values[] = $value;
    }
    $this->exec('INSERT ' . ($ignore ? 'IGNORE ' : '') . 'INTO `' . $this->escape($table) . '` (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')');
  }
//更新数据库表，其中$set指数据库表格中要改变的列以及对应的值。$where 表示限制条件
  public function update($table, $set, $where = null)
  {
    $values = array();
    foreach ($set as $key => $value) {
      $field = true;
      if (is_array($value) && isset($value['value'])) {
        if (isset($value['escape']) && false === $value['escape']) {
          $field = false;
        }
        $value = $value['value'];
      }
      $value = $this->escape($value);
      if ($field) {
        $value = '"' . $value . '"';
      }
      $values[] = '`' . $this->escape($key) . '` = ' . $value;
    }
    if (isset($where) && is_array($where)) {
      foreach ($where as $key => $value) {
        $this->where($key, $value);
      }
    }
    $where = $this->_compileWhere('WHERE');
    $this->_activeRec['WHERE'] = array();
    $this->exec('UPDATE `' . $this->escape($table) . '` SET ' . implode(', ', $values) . $where);
  }

  public function delete($table, $where = null)
  {
    if (isset($where) && is_array($where)) {
      foreach ($where as $key => $value) {
        $this->where($key, $value);
      }
    }
    $where = $this->_compileWhere('WHERE');
    $this->_activeRec['WHERE'] = array();
    $this->exec('DELETE FROM `' . $this->escape($table) . '`' . $where);
  }

  public function select($select)
  {
    $select = explode(',', $select);
    foreach ($select as $field) {
      $this->_activeRec['SELECT'][] = trim($field);
    }
    return $this;
  }

  public function distinct()
  {
    $this->_activeRec['DISTINCT'] = true;
    return $this;
  }

  public function from($table)
  {
    if (!is_array($table)) {
      $table = array($table);
    }
    foreach ($table as $alias => $name) {
      if (!is_int($alias)) {
        $name .= ' AS ' . $alias;
      }
      $this->_activeRec['FROM'][] = $name;
    }
    return $this;
  }

  public function join($table, $cond, $type = 'inner')
  {
    static $types = array(
      'inner' => 'INNER JOIN',
      'left'  => 'LEFT JOIN',
      'right' => 'RIGHT JOIN',
      'full'  => 'FULL JOIN',
    );
    if (strpos($cond, '=')) {
      $cond = 'ON ' . $cond;
    } else {
      $cond = 'USING (' . $cond . ')';
    }

    if (!is_array($table)) {
      $table = array($table);
    }
    foreach ($table as $alias => $name) {
      if (!is_int($alias)) {
        $name .= ' AS ' . $alias;
      }
      $joinItem = $types[$type] . ' ' . $name . ' ' . $cond;
      if (!in_array($joinItem, $this->_activeRec['JOIN'])) {
      	$this->_activeRec['JOIN'][] = $joinItem;
      }
    }
    return $this;
  }

  public function limit($limit, $offset = null)
  {
    $this->_activeRec['LIMIT'] = $limit;
    $this->_activeRec['OFFSET'] = $offset;
    return $this;
  }

  public function limitPage($limit, $page = 1)
  {
    $offset = $limit * ($page - 1);
    $this->limit($limit, $offset);
    return $this;
  }

  public function where($cond, $value, $type = 'and', $escape = true, $recKey = 'WHERE')
  {
    static $types = array(
      'and' => 'AND',
      'or'  => 'OR',
    );
    static $ops = array('>=', '<=', '<>', '!=', '=', '>', '<', 'IN', 'LIKE', 'REGEXP');
    $cond = trim($cond);
    if (false !== ($eqPos = strpos($cond, ' '))) {
      $field = trim(substr($cond, 0, $eqPos));
      $op = strtoupper(trim(substr($cond, $eqPos + 1)));
      if (!in_array($op, $ops)) {
        $op = '=';
      }
    } else {
      $field = $cond;
      $op = '=';
    }
    if ($op == 'IN') {
      if (is_array($value)) {
        foreach ($value as &$v) {
          $v = '"' . $this->escape($v) . '"';
        }
        $value = '(' . implode(', ', $value) . ')';
      } else {
        $op = '=';
        $value = '"' . $this->escape($value) . '"';
      }
    } else {
      if ($escape) {
        $value = '"' . $this->escape($value) . '"';
      }
    }
    $this->_activeRec[$recKey][] = array(
      'field' => $field,
      'op'    => $op,
      'type'  => $types[$type],
      'value' => $value,
    );
    return $this;
  }

  public function having($cond, $value, $type = 'and', $escape = true)
  {
    return $this->where($cond, $value, $type, $escape, 'HAVING');
  }

  public function orderby($order)
  {
    $order = explode(',', $order);
    foreach ($order as $field) {
      $this->_activeRec['ORDERBY'][] = trim($field);
    }
    return $this;
  }

  public function groupby($group)
  {
    $group = explode(',', $group);
    foreach ($group as $field) {
      $this->_activeRec['GROUPBY'][] = trim($field);
    }
    return $this;
  }

  public function get($table = null, $limit = null, $offset = null)
  {
    if (isset($table)) {
      $this->from($table);
    }
    if (isset($limit)) {
      $this->limit($limit, $offset);
    }
    $sql = $this->_compileSelect();
    $this->_resetSelect();
    return $this->query($sql);
  }

  private function _compileWhere($recKey = 'WHERE')
  {
    $sql = '';
    if ($recKey == 'WHERE' || $recKey == 'HAVING') {
      $rec = $this->_activeRec;
      if (isset($rec[$recKey][0])) {
        $sql .= ' ' . $recKey;
        foreach ($rec[$recKey] as $num => $cond) {
          if ($num > 0){
            $sql .= ' ' . $cond['type'];
          }
          if (false === strpos($cond['field'], '.')) {
            $sql .= ' `' . $cond['field'] . '`';
          } else {
            $sql .= ' ' . $cond['field'];
          }
          $sql .=  ' ' . $cond['op'] . ' ' . $cond['value'];
        }
      }
    }
    return $sql;
  }

  private function _compileSelect()
  {
    $sql = 'SELECT';
    $rec = $this->_activeRec;
    if ($rec['DISTINCT']) {
      $sql .= ' DISTINCT';
    }
    if (isset($rec['SELECT'][0])) {
      $sql .= ' ' . implode(', ', $rec['SELECT']);
    } else {
      $sql .= ' *';
    }
    if (isset($rec['FROM'][0])) {
      $sql .= ' FROM ' . implode(', ', $rec['FROM']);
    }
    if (isset($rec['JOIN'][0])) {
      $sql .= ' ' . implode(' ', $rec['JOIN']);
    }
    $sql .= $this->_compileWhere('WHERE');
    if (isset($rec['GROUPBY'][0])) {
      $sql .= ' GROUP BY ' . implode(', ', $rec['GROUPBY']);
    }
    if (isset($rec['ORDERBY'][0])) {
      $sql .= ' ORDER BY ' . implode(', ', $rec['ORDERBY']);
    }
    $sql .= $this->_compileWhere('HAVING');
    if (isset($rec['LIMIT'])) {
      $sql .= ' LIMIT ' . (isset($rec['OFFSET']) && $rec['OFFSET'] > 0 ? ($rec['OFFSET'] . ', ') : '') . $rec['LIMIT'];
    }
    return $sql;
  }
}

final class Bl_Db_Result
{
  private $_result;

  public function __construct($result)
  {
    $this->_result = $result;
  }

  public function all($object = true)
  {
    $result = array();
    if ($object) {
      while ($data = mysql_fetch_object($this->_result)) {
        $result[] = $data;
      }
    } else {
      while ($data = mysql_fetch_assoc($this->_result)) {
        $result[] = $data;
      }
    }
    unset($data);
    return $result;
  }

  public function allWithKey($key, $object = true)
  {
    $result = array();
    if ($object) {
      while ($data = mysql_fetch_object($this->_result)) {
        $result[$data->$key] = $data;
      }
    } else {
      while ($data = mysql_fetch_assoc($this->_result)) {
        $result[$data[$key]] = $data;
      }
    }
    unset($data);
    return $result;
  }

  public function column($index = 0)
  {
    $result = array();
    $type = is_int($index) ? MYSQL_NUM : MYSQL_ASSOC;
    while ($data = mysql_fetch_array($this->_result, $type)) {
      $result[] = $data[$index];
    }
    unset($data);
    return $result;
  }

  public function columnWithKey($key, $index = 0)
  {
    $result = array();
    while ($data = mysql_fetch_array($this->_result)) {
      $result[$data[$key]] = $data[$index];
    }
    unset($data);
    return $result;
  }

  public function row($object = true)
  {
    return $object ? mysql_fetch_object($this->_result) : mysql_fetch_assoc($this->_result);
  }

  public function one($index = 0)
  {
    $data = mysql_fetch_array($this->_result);
    return false === $data ? false : $data[$index];
  }
}

final class Bl_Db_Exception extends Exception
{
  private $_sql;

  public function __construct($message, $code = 0, $sql = '')
  {
    global $db;
    header('HTTP/1.1 503 Service Unavailable');
    parent::__construct($message, $code);
    $this->_sql = $sql;
    if (Bl_Config::get('log.db', true) && $db->actived()) {
      log::save('db', $message, $this);
    }
  }

  public function getQuery()
  {
    return $this->_sql;
  }
}