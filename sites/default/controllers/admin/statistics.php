<?php
class Admin_Statistics_Controller extends Bl_Controller
{
  private $_statisticsInstance;

  public static function __permissions()
  {
    return array(
      'customers statistics',
      'orders statistics',
      'sales statistics',
      'members statistics',
      'buy banner statistics',
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_statisticsInstance = Statistics_Model::getInstance();
  }

  function indexAction()
  {
  	gotourl('admin/statistics/customers');
  }

  function customersAction()
  {
    if (!access('customers statistics')) {
      goto403('Access Denied.');
    }
    $members = $this->_statisticsInstance->membersSta();
    $orders = $this->_statisticsInstance->ordersSta();
    $amount = $this->_statisticsInstance->amountSta();
    $this->view->render('admin/statistics/customers.phtml', array(
      'members' => $members,
      'orders' => $orders,
      'amount' => $amount,
    ));
  }
  
  function ordersAction()
  {
    if (!access('orders statistics')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $post = $_POST;
    }
    $startTime = isset($post['startTime']) ? strtotime($post['startTime']) : null;
    $endTime = isset($post['endTime']) ? strtotime($post['endTime']) : null;
    $orders = $this->_statisticsInstance->ordersSta($startTime, $endTime);
    $amount = $this->_statisticsInstance->amountSta($startTime, $endTime);
    $this->view->addJs(url('scripts/widget/admin/widget.js'));
    $this->view->addJs(url('scripts/My97DatePicker/WdatePicker.js'));
    $this->view->render('admin/statistics/orders.phtml', array(
      'orders' => $orders,
      'amount' => $amount,
      'startTime' => $startTime,
      'endTime' => $endTime,
    ));
  }
  
  function salesAction($page = 1)
  {
    if (!access('sales statistics')) {
      goto403('Access Denied.');
    }
    $pageRows = 15;
    $datas = $this->_statisticsInstance->salesSta($page, $pageRows);
    $this->view->render('admin/statistics/sales.phtml', array(
      'page' => $page,
      'pageRows' => $pageRows,
      'list' => $datas[1],
      'pagination' => pagination('admin/statistics/sales/%d', $datas[0], $pageRows, $page),
    ));
  }
  
  function membersAction($page = 1)
  {
    if (!access('members statistics')) {
      goto403('Access Denied.');
    }
    $pageRows = 15;
    $datas = $this->_statisticsInstance->usersordersSta($page, $pageRows);
    $this->view->render('admin/statistics/members.phtml', array(
      'page' => $page,
      'pageRows' => $pageRows,
      'list' => $datas[1],
      'pagination' => pagination('admin/statistics/members/%d', $datas[0], $pageRows, $page),
    ));
  }
  
  function buybannerAction($page = 1)
  {
    if (!access('members statistics')) {
      goto403('Access Denied.');
    }
    $pageRows = 15;
    $datas = $this->_statisticsInstance->buybannerSta($page, $pageRows);
    $this->view->render('admin/statistics/buybanner.phtml', array(
      'page' => $page,
      'pageRows' => $pageRows,
      'list' => $datas[1],
      'pagination' => pagination('admin/statistics/buybanner/%d', $datas[0], $pageRows, $page),
    ));
  }
}