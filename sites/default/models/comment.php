<?php
class Comment_Model extends Bl_Model
{
  /**
   * @return Comment_Model
   */

  const MESSAGE_WATTING = 0;//等待处理的数据
  const MESSAGE_NOMAL = 1;//正常
  const MESSAGE_DEL = 2;//特殊状态的数据

  public $commnetType = array(
    '1' => 'products',
    '2' => 'productMessages',
    '3' => 'orders',
    '4' => 'articles',
  );

  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取所有评论信息列表
   * @return object 评论列表信息
   */
  public function getCommentsList($page, $pageRows, $status = null, $filter = array())
  {
    global $db;
    $rowsStart = ($page-1) * $pageRows;
    $sql = 'SELECT c.* FROM comments c JOIN users u ON c.uid = u.uid AND c.replyid=0 ';
    if (isset($status) && $status != 'all') {
      $sql .= 'and c.status = "' . $db->escape($status) . '" ';
    }
    if (isset($filter['orderby']) && $filter['orderby']) {
      $sql .= ' ORDER BY ' . $filter['orderby'];
    } else {
      $sql .= ' ORDER BY c.timestamp DESC ';
    }
    if($pageRows){
      $sql.=" limit $rowsStart, $pageRows ";
    }
    $result = $db->query($sql);
    $arr = $result->allWithKey('cid');
    return $arr;
  }

  /**
   * 获取信息列表总数
   * @return int
   */
  public function getCountCommentsList($status = null)
  {
    global $db;
    $sql = 'SELECT COUNT(*) num FROM comments c JOIN users u ON c.uid = u.uid AND c.replyid=0 ';
		if (isset($status) && $status != 'all') {
		  $sql .= 'and c.status = "' . $db->escape($status) . '" ';
		}
		$result = $db->query($sql);
		return $result->one();
  }

  /**
   * 获取一个评论的详细信息
   * @return object 评论详细信息
   */
  public function getCommentInfo($cid)
  {
    global $db;
    static $list = array();
    if (!isset($list[$cid])) {
      $cacheId = 'comment-' . $cid;
      if ($cache = cache::get($cacheId)) {
        $arr = $cache->data;
      } else {
        $sql = 'SELECT * from comments where cid = "' . $db->escape($cid) . '"';
        $result = $db->query($sql);
		    $arr = $result->row();
		    if (isset($arr) && $arr && $arr->replyid == 0) {
		      $sql = 'SELECT p.name pname,p.pid,u.nickname FROM products_comments pc, products p, users u WHERE
		      pc.pid = p.pid AND u.uid = "'. $arr->uid .'" AND pc.cid = "' . $db->escape($cid) . '"';
		      $result = $db->query($sql);
		      $arr2 = $result->row();
		      if($arr2){
			      $arr->pname = $arr2->pname;
			      $arr->pid = $arr2->pid;
			      $arr->nickname = $arr2->nickname;
		      }
		      $arr->replayComment = $this->getReplayInfo($cid);
		    }
		    cache::save($cacheId, $arr);
		  }
    } else {
      $arr = $list[$cid];
    }
    return $arr;
  }
  
  
  /**
   * 
   * Enter description here ...
   * @param unknown_type $cid
   */
  function getReplyCommentInfo($cid){
    global $db;

    $cacheId = 'comment-reply' . $cid;
    if ($cache = cache::get($cacheId)) {
      $arr = $cache->data;
    } else{
      $sql = 'SELECT * from comments where cid = "' . $db->escape($cid) . '"';
      $result = $db->query($sql);
      $arr = $result->row();
      cache::save($cacheId, $arr);
    }
    return $arr;
  }
  
  
  

  /**
   * 获得回复的评论内容
   */
  function getReplayInfo($cid)
  {
    global $db;
    $list = array();
    if (!isset($list[$cid])) {
      $cacheId = 'comment-replay-' . $cid;
      if ($cache = cache::get($cacheId)) {
        $arr = $cache->data;
      } else {
        $sql = 'SELECT * FROM comments WHERE replyid = "' . $db->escape($cid) . '"';
        $result = $db->query($sql);
        $arr = $result->all();
        cache::save($cacheId, $arr);
      }
    } else {
      $arr = $list[$cid];
    }
    return $arr;
  }

  /**
   * 新增一个评论
   * @return boolean
   */
  public function insertComment($uid, $subject, $comment, $photo_paths, $nickname = '', $status = 0, $ip = '', $replyid = 0)
  {
    global $db;
    if ($replyid != 0) {
      $cacheId = 'comment-replay-' . $replyid;
      $cacheId1 = 'comment-' . $replyid;
      cache::remove($cacheId);
      cache::remove($cacheId1);
    }
    if($ip==''){
      $ip = $_SERVER['REMOTE_ADDR'];
    }
    $timestamp = time();
    $sql = 'INSERT INTO comments SET uid = "' . $db->escape($uid) . '", replyid = "' .$db->escape($replyid). '", nickname = "'.$db->escape($nickname).'",
      subject = "' . $db->escape($subject) . '", comment = "' . $db->escape($comment) . '", photo_paths = "' . $db->escape(serialize($photo_paths)) . '",status = "'.$db->escape($status).'",
      ip = "' . $db->escape($ip) . '", timestamp = "' . $db->escape($timestamp) . '";';
    $db->exec($sql);
    return $db->lastInsertId();
  }

  /**
   * 新增商品与评论的关系
   * @return boolean
   */
  public function insertProductComments($pid, $cid)
  {
    global $db;
    $sql = 'INSERT INTO products_comments SET pid = "' . $db->escape($pid) . '", cid = "' . $db->escape($cid) . '" ';
    return $db->exec($sql);
  }

  /**
   * 删除一个评论
   * @return boolean
   */
  public function deleteComment($cid)
  {
    global $db;
    $commentInfo = $this->getCommentInfo($cid);
    if (isset($commentInfo) && $commentInfo) {
      $db->delete('comments', array('cid' => $cid));
      $db->delete('comments', array('replyid' => $cid));
      if ($commentInfo->type) {
        $db->delete($this->commnetType[$commentInfo->type] . '_comments', array('cid' => $cid));
      }
      cache::remove('comment-' . $cid);
      cache::remove('comment-replay-' . $cid);
      return true;
    } else {
      return false;
    }
  }

  /**
   * 按照条件删除评论
   * @return boolean
   */
  public function deleteCommentCondition($status)
  {
    global $db;
    $db->where(status, $status);
    $result = $db->get('comments');
    $list = $result->all();
    foreach ($list as $k => $v) {
    	cache::remove('comment-' . $v->cid);
    }
    $sql = "DELETE FROM comments WHERE 1 ";
    if($status!==""){
      $sql .= "AND status = '" . $db->escape($status) . "' ";
    }
    cache::remove('comment-' . $cid);
    return $db->exec($sql);
  }

  /**
   * 删除商品于评论的关系
   * @return boolean
   */
  public function deleteProductComments($cid)
  {
    global $db;
    $sql = "DELETE FROM products_comments WHERE cid IN (" . $db->escape($cid) . ") ";
    return $db->exec($sql);
    
  }

  /**
   * 按照条件删除商品于评论的关系
   * @return boolean
   */
  public function deleteProductCommentsCondition($status)
  {
    global $db;
    $sql = "DELETE FROM products_comments WHERE cid IN (SELECT cid FROM comments WHERE 1 ";
    if($status!==""){
      $sql .= "AND status = '" . $db->escape($status) . "' ";
    }
    $sql .= ") ";
    return $db->exec($sql);
  }

  /**
   * 修改一个评论
   * @return boolean
   */
  public function updateCommentStatus($cid, $status)
  {
    global $db;
    $sql = "UPDATE comments SET status = '" . $db->escape($status) . "' WHERE cid IN (" . $db->escape($cid) . ") ";
    cache::remove('comment-' . $cid);
    return $db->exec($sql);
  }

  public function updateComment($cid, $uid, $subject, $comment, $nickname = '', $status = 0, $ip = '')
  {
    global $db;
    $set = array(
      'uid' => $uid,
      'nickname' => $nickname,
      'subject' => $subject,
      'comment' => $comment,
      'status' => $status,
      'ip' => $ip,
    );
    $where = array('cid' => $cid);
    $db->update('comments', $set, $where);
  }


  /**
   * 按照条件修改评论
   * @return boolean
   */
  public function updateCommentCondition($source_status,$status)
  {
    global $db;
    $db->where(status, $source_status);
    $result = $db->get('comments');
    $list = $result->all();
    foreach ($list as $k => $v) {
      cache::remove('comment-' . $v->cid);
    }
    $sql = "UPDATE comments SET status = '" . $db->escape($status) . "' WHERE 1 ";
    if($source_status!==""){
      $sql .= "and status = '" . $db->escape($source_status) . "'  ";
    }
    return $db->exec($sql);
  }

  /**
   * 检查一个评论是否存在
   * @return boolean
   */
  public function CheckCommentCidExist($cid)
  {
    global $db;
    $sql = 'SELECT * FROM comments WHERE cid = "' . $db->escape($cid) . '" ';
    $result = $db->query($sql);
    return $result->one();
  }

  /**
   * 获取一个商品的评论
   *@param $pid 商品ID
   *@return array
   */
  public function getCommentsListByProductId($pid, $filter = array('status' => 1), $page=1, $pageRows = 0)
  {
    global $db;
    $db->select('c.*');
    foreach ($filter as $key => $value) {
      if (isset($value) && $value !== '' && $value !== false && $key != 'orderby') {
        $db->where('c.'.$key, $value);
      }
    }
    $db->where('pc.pid', $pid);
    $db->join('products_comments pc', 'pc.cid = c.cid');
    $db->join('users u', 'u.uid = c.uid');
    if (isset($filter['orderby']) && $filter['orderby']) {
      $db->orderby($filter['orderby']);
    } else {
      $db->orderby('c.timestamp DESC');
    }
    if ($pageRows) {
      $db->limitPage($pageRows, $page);
    }
    $result = $db->get('comments c');
    $arr = $result->all();
    foreach ($arr as $comment)
    {
        $comment->photo_paths = unserialize($comment->photo_paths);
    }
    return $arr;
  }

  /**
   * 获取一个商品的评论
   *@param $pid 商品ID
   *@return array
   */
  public function getCommentsCountByProductId($pid, $filter = array('status' => 1))
  {
    global $db;
    $db->select('COUNT(0)');
    foreach ($filter as $key => $value) {
      if (isset($value) && $value !== '' && $value !== false) {
        $db->where('c.'.$key, $value);
      }
    }
    $db->where('pc.pid', $pid);
    $db->join('products_comments pc', 'pc.cid = c.cid');
    $db->join('users u', 'u.uid = c.uid');
    $result = $db->get('comments c');
    return $result->one();
  }
  
  
  /**
   * Set Useful/Useless Attribute for a product/comment.
   * Enter description here ...
   * @param unknown_type $pid
   * @param unknown_type $cid
   */
  public function getUserAttitude($pid, $cid){
    global $db;
    $db->select('*');
    $db->where('pid', $pid);
    $db->where('cid', $cid);
    $db->get('products_comments');
    $arr = $result->one();
    return $arr;
  }
  
  /**
   * 
   * Enter description here ...
   * @param unknown_type $pid
   * @param unknown_type $cid
   * @param unknown_type $useful
   */
  
  public function updateUserAttitude($pid, $cid, $useful = true)
  {
    global $db;
    $set = array();
    
    if($useful == true){
      $set['useful'] = 'useful + 1';
    }else{
      $set['useless'] = 'useless + 1';
    }

    $db->update('products_comments', $set, array('pid' => $pid, 'cid' =>$cid));
    return $db->lastInsertId();
  }

  
  
  

  public function getWebsiteMessageList($page = 1, $pageRows = 10, $status = null)
  {
    global $db;
    if (isset($status)) {
      $db->where('status', $status);
    }
    $db->orderby('gbid DESC');
    if ($page) {
      $db->limitPage($pageRows, $page);
    }
    $result = $db->get('guestbook');
    return $result->all();
  }

  public function getWebsiteMessageCount($status = null)
  {
    global $db;
    if (isset($status)) {
      $db->where('status', $status);
    }
    $db->select('COUNT(0)');
    $result = $db->get('guestbook');
    return $result->one();
  }

  public function getWebsiteMessageInfo($gbid)
  {
    global $db;
    $db->where('gbid', $gbid);
    $result = $db->get('guestbook');
    $msginfo = $result->row();
    $msginfo->data = isset($msginfo->data) ? unserialize($msginfo->data) : null;
    return $msginfo;
  }

  public function insertWebsiteMessage($post)
  {
    global $db;
    $post['ip'] = isset($post['ip']) ?  $post['ip'] : $_SERVER['REMOTE_ADDR'];
    $set = array(
      'nickname' => isset($post['nickname']) ? $post['nickname'] : null,
      'email' => isset($post['email']) ? $post['email'] : null,
      'subject' => isset($post['subject']) ? $post['subject'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'data' => isset($post['data']) ? $post['data'] : null,
      'status' => isset($post['status']) ? $post['status'] : null,
      'ip' => isset($post['ip']) ? $post['ip'] : null,
      'timestamp' => TIMESTAMP,
    );
    $db->insert('guestbook', $set);
    return $db->lastInsertId();
  }

  public function updateWebsiteMessageList($gbids, $post)
  {
    $gbids = explode(',', $gbids);
    if (is_array($gbids) && count($gbids) >0) {
      foreach ($gbids as $key => $gbid) {
        $this->updateWebsiteMessage($gbid, $post);
      }
    }
  }

  public function deleteWebsiteMessageList($gbids)
  {
    $gbids = explode(',', $gbids);
    if (is_array($gbids) && count($gbids) >0) {
      foreach ($gbids as $key => $gbid) {
        $this->deleteWebsiteMessage($gbid);
      }
    }
  }

  public function deleteWebsiteMessage($gbid)
  {
    global $db;
    $db->delete('guestbook', array('gbid' => $gbid));
  }

  public function updateWebsiteMessage($gbid, $post)
  {
    global $db;
    $set = array(
      'nickname' => isset($post['nickname']) ? $post['nickname'] : null,
      'email' => isset($post['email']) ? $post['email'] : null,
      'subject' => isset($post['subject']) ? $post['subject'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'data' => isset($post['data']) ? $post['data'] : null,
      'status' => isset($post['status']) ? $post['status'] : null,
      'ip' => isset($post['ip']) ? $post['ip'] : null,
      'timestamp' => TIMESTAMP,
    );
    $set = array_filter($set, "Common_Model::filterArray");
    $db->update('guestbook', $set, array('gbid' => $gbid));
    return $db->lastInsertId();
  }

  /**
   * 新增订单评论
   * @param $oid 订单ID
   * @param $post 评论内容
   */
  public function insertOrdersComments($oid, $post)
  {
    global $db;
    $set = array(
      'nickname' => isset($post['nickname']) ? $post['nickname'] : null,
      'email' => isset($post['email']) ? $post['email'] : null,
      'subject' => isset($post['subject']) ? $post['subject'] : null,
      'content' => isset($post['content']) ? $post['content'] : null,
      'data' => isset($post['data']) ? $post['data'] : null,
      'status' => isset($post['status']) ? $post['status'] : null,
      'ip' => isset($post['ip']) ? $post['ip'] : null,
      'timestamp' => TIMESTAMP,
    );
    $set['type'] = 3;
    $db->insert('comments', $set);
    $cid = $db->lastInsertId();
    if (isset($cid) && $cid) {
      $set = array(
        'oid' => $oid,
        'cid' => $cid,
      );
      $db->insert('orders_comments', $set);
      return $cid;
    } else {
      return false;
    }
  }

  /**
   * 获取订单评论列表
   * $param $post 过滤条件
   * $page 页数
   * $pageRows 每页显示多少条数
   */
  public function getOrdersCommentsList($oid, $filter = array(), $page = 1, $pageRows = 20)
  {
    global $db;
    $db->where('oc.oid', $oid);
    if (is_array($filter) && !empty($filter)) {
      foreach ($filter as $k => $v) {
        if ($v) {
          $db->where('c.'.$k, $v);
        }
      }
    }
    $db->join('orders_comments oc', 'oc.cid = c.cid');
    $db->limitPage($pageRows, $page);
    $db->orderby('c.timestamp DESC');
    $result = $db->get('comments c');
    return $result->allWithKey('c.cid');
  }

  /**
   * 获取订单评论总数
   * $param $post 过滤条件
   * $page 页数
   * $pageRows 每页显示多少条数
   */
  public function getOrdersCommentsCount($oid, $filter = array())
  {
    global $db;
    $db->select('COUNT(0)');
    $db->where('oc.oid', $oid);
    if (is_array($filter) && !empty($filter)) {
      foreach ($filter as $k => $v) {
        if ($v) {
          $db->where('c.'.$k, $v);
        }
      }
    }
    $db->join('orders_comments oc', 'oc.cid = c.cid');
    $result = $db->get('comments c');
    return $result->one();
  }
  
  // 把每个商品中的评论加上商品的属性字段（颜色、尺寸、……
  public function addOrderitemPropertyToComments($comments, $pid){
  	global $db;
  	
  	foreach ($comments as $v){
  		$db->select("oi.data");
  		$db->where('oi.pid', $pid);
  		$db->join('orders o', 'oi.oid=o.oid');
  		$uid = $v->uid;
  		$db->where('o.uid', $uid);
  		$db->where('o.status_payment', 1);
  		$db->orderby('o.created desc');
  		$results = $db->get('orders_items oi');
  		$commentjson = $results->one();
  		if($commentjson){
  			$propertyarray = unserialize($commentjson);
  			$v->propertys = $propertyarray;
  		}
  	}
  	return $comments;
  }

}