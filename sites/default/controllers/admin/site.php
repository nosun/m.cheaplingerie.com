<?php
class Admin_Site_Controller extends Bl_Controller
{
  private $_siteInstance;

  public static function __permissions()
  {
    return array(
      'manage currency',
      'manage payment',
      'manage shipping',
      'manage widget',
      'pagevariablestheme',
      'manage adphoto',
      'manage carousel photo',
      'manage robots',
      'manage update',
      'setting',
      'manage templateedit'
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_siteInstance = Site_Model::getInstance();
  }

  public function indexAction()
  {
    $this->templatesAction();
  }

  public function templatesAction()
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $templates = $this->_siteInstance->getTemplatesList();
    $template = $this->_siteInstance->getTemplateInfo(Bl_Config::get('template', HOSTNAME));
    $this->view->render('admin/site/templateslist.phtml', array(
      'template' => $template,
      'templates' => $templates,
    ));
  }

  public function settemplateAction($template)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $templates = $this->_siteInstance->getTemplatesList();
    if (isset($templates[$template])) {
      Bl_Config::set('template', $template);
      Bl_Config::save();
      setMessage('模板设置成功');
      if (isset($_SESSION['preview'])) {
        unset($_SESSION['preview']);
      }
    }
    gotoUrl('admin/site/templates');
  }

  public function previewAction($template)
  {
    $_SESSION['preview'] = $template;
    gotoUrl('');
  }

  public function templateeditAction($templateName = null)
  {
    if (!access('manage templateedit')) {
      goto403('Access Denied.');
    }
    $template = $this->_siteInstance->getTemplateInfo(Bl_Config::get('template', HOSTNAME));
    $files = $this->_siteInstance->getTemplateFilesStatus($template->template);
    if (isset($templateName)) {
      if ($this->isPost()) {
        if ($templateName == 'new' && isset($_POST['newfilename'])) {
          $file = trim($_POST['newfilename']);
          if (isset($files[$file])) {
            setMessage('模板文件名已存在');
            gotoUrl('admin/site/templateedit/new');
          }
          if (getFileExtname($file) != 'phtml') {
            setMessage('模板文件名无效');
            gotoUrl('admin/site/templateedit/new');
          }
        } else if (false !== ($file = base64_decode($templateName))) {
          if (getFileExtname($file) != 'phtml' || !isset($files[$file])) {
            setMessage('模板文件名无效');
            gotoUrl('admin/site/templateedit');
          }
        }
        $this->_siteInstance->saveTemplateFileContent($template->template, $file, $_POST['content']);
        setMessage('Template has been saved.');
        gotoUrl('admin/site/templateedit');
      }
      if ($templateName == 'new') {
        $file = '';
        $content = '';
      } else if (false !== ($file = base64_decode($templateName))) {
        if (getFileExtname($file) != 'phtml' || !isset($files[$file])) {
          setMessage('模板文件名无效');
          gotoUrl('admin/site/templateedit');
        }
        $content = $this->_siteInstance->getTemplateFileContent($template->template, $file);
      }
      $this->view->render('admin/site/templateinfo.phtml', array(
        'template' => $template,
        'file' => $file,
        'content' => $content,
      ));
    } else {
      $this->view->render('admin/site/templatefileslist.phtml', array(
        'template' => $template,
        'templateFiles' => $files,
      ));
    }
  }

  public function ajaxgettemplatecontentAction($file, $loadDefault = 0)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $file = explode('|', $file);
    if (!isset($file[1])) {
      goto404('Argument is invalid.');
    }
    $template = $this->_siteInstance->getTemplateInfo(Bl_Config::get('template', HOSTNAME));
    $templateName = $file[0];
    $file = base64_decode($file[1]);
    $jsonArray = array(
      'error' => -1,
      'msg' => 'unknown error.',
    );
    if ($template->template == $templateName) {
      if (false !== $result = $this->_siteInstance->getTemplateFileContent($template->template, $file, $loadDefault)) {
        $jsonArray += $result;
        $jsonArray['error'] = 0;
        $jsonArray['msg'] = '';
      }
    }
    echo json_encode($jsonArray);
    exit;
  }

  public function templatedelAction($templateName = null)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    if (false !== ($file = base64_decode($templateName))) {
      $template = $this->_siteInstance->getTemplateInfo(Bl_Config::get('template', HOSTNAME));
      $files = $this->_siteInstance->getTemplateFilesStatus($template->template);
      if (getFileExtname($file) != 'phtml' || !isset($files[$file])) {
        setMessage('模板文件名无效');
      } else {
        $this->_siteInstance->deleteTemplateFile($file);
        setMessage('模板删除成功.');
      }
    }
    gotoUrl('admin/site/templateedit');
  }

  public function unpreviewAction()
  {
    if (isset($_SESSION['preview'])) {
      unset($_SESSION['preview']);
    }
    echo '<script type="text/javascript">window.close()</script>';
  }

  public function getCurrencyListAction()
  {
    if (!access('manage currency')) {
      goto403('Access Denied.');
    }
    $currencyList = $this->_siteInstance->getCurrenciesList();
    $this->view->render('admin/site/getcurrencylist.phtml', array(
      'currencyList' => isset($currencyList) ? $currencyList : null,
    ));
  }

  public function editCurrencyAction($name = null)
  {
    if (!access('manage currency')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $post = $_POST;
      $post['default'] = isset($post['default']) ? '1' : '0';
      $post['visible'] = isset($post['visible']) ? '1' : '0';
      if ($name) {
        if ($this->_siteInstance->getCurrencyInfo($name)) {
          $result = $this->_siteInstance->updateCurrency($name, $post);
          if ($result) {
            setMessage('修改货币成功');
            gotoUrl('admin/site/getcurrencylist');
          } else if ($result == 0) {
            setMessage('没有任何更改');
            gotoUrl('admin/site/getcurrencylist');
          } else {
            setMessage('修改货币失败', 'error');
          }
        } else {
          setMessage('不存在该货币值', 'error');
        }
      } else {
        if ($post['name']) {
          if (!$this->_siteInstance->getCurrencyInfo($post['name'])) {
            if (!$this->_siteInstance->insertCurrency($post)) {
              setMessage('新增货币成功');
              gotoUrl('admin/site/getcurrencylist');
            } else {
              setMessage('新增货币失败', 'error');
            }
          } else {
            setMessage('货币类型已经存在', 'error');
          }
        } else {
          setMessage('请选择货币类型', 'error');
        }
      }
    }
    if ($name) {
      $currencyInfo = $this->_siteInstance->getCurrencyInfo($name);
    }
    $this->view->render('admin/site/getcurrencyinfo.phtml', array(
      'currencyInfo' => isset($currencyInfo) ? $currencyInfo : null,
    ));
  }

  public function deleteCurrencyAction($name)
  {
    if (!access('manage currency')) {
      goto403('Access Denied.');
    }
    if ($name) {
      if ($this->_siteInstance->getCurrencyInfo($name)) {
        if ($this->_siteInstance->deleteCurrency($name)){
          setMessage('删除成功');
        } else {
          setMessage('删除错误', 'error');
        }

      } else {
        setMessage('参数错误', 'error');
      }
    } else {
      setMessage('参数错误', 'error');
    }
    gotoUrl('admin/site/getcurrencylist');
  }

  public function shippingListAction()
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    $shippingInstance = Shipping_Model::getInstance();
    $shippingList = $shippingInstance->shippingList();
    $this->view->render('admin/site/shippinglist.phtml', array(
        'shippingList' => isset($shippingList) ? $shippingList : null,
    ));
  }

  public function installshippingAction($name)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $set = Bl_Config::get('shipping.' . $name, array('status' => false, 'visible' => true));
    $set['status'] = true;
    Bl_Config::set('shipping.' . $name, $set);
    Bl_Config::save();
    gotourl('admin/site/shippinglist');
  }

  public function uninstallshippingAction($name)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $set = Bl_Config::get('shipping.' . $name, array('status' => false, 'visible' => true));
    $set['status'] = false;
    Bl_Config::set('shipping.' . $name, $set);
    Bl_Config::save();
    gotourl('admin/site/shippinglist');
  }

  public function showshippingAction($name)
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    $set = Bl_Config::get('shipping.' . $name, array('status' => false, 'visible' => true));
    $set['visible'] = true;
    Bl_Config::set('shipping.' . $name, $set);
    Bl_Config::save();
    gotourl('admin/site/shippinglist');
  }

  public function hideshippingAction($name)
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    $set = Bl_Config::get('shipping.' . $name, array('status' => false, 'visible' => true));
    $set['visible'] = false;
    Bl_Config::set('shipping.' . $name, $set);
    Bl_Config::save();
    gotourl('admin/site/shippinglist');
  }

  public function editshippingAction($name)
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $post = $_POST;
      $set = Bl_Config::get('shipping.'.$name, array('status' => false, 'visible' => true));
      $set['name_f'] = $post['name_f'];
      $set['descripe_f'] = $post['descripe_f'];
      $set['weight'] = intval($post['weight']);
      Bl_Config::set('shipping.' . $name, $set);
      Bl_Config::save();
      setMessage('保存成功');
      gotourl('admin/site/shippinglist');
    } else {
      $shippingInstance = Bl_Plugin::getInstance('shipping', $name);
      $config = Bl_Config::get('shipping.' . $name, array('status' => false, 'visible' => true));
      $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
      $this->view->render('admin/site/shippinginfo.phtml', array(
        'config' => isset($config) ? $config : null,
      ));
    }

  }

  public function listshippingareaAction($name)
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    $shippingInstance = Bl_Plugin::getInstance('shipping', $name);
    $config = Bl_Config::get('shipping.'.$name, 0);
    $countries = $this->_siteInstance->getCountries();
    $provinces = $this->_siteInstance->getProvinces(key($countries));
    $this->view->render('admin/site/shippingarealist.phtml', array(
      'config' => isset($config) ? $config : null,
      'name' => $name,
      'countries' => $countries,
      'provinces' => $provinces
    ));
  }

  public function editshippingareaAction($name, $key = null)
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $post = $_POST;
      if ($post['area']) {
        $set = Bl_Config::get('shipping.'.$name, 0);
        $set['status'] = 1;
        $key = $post['key'];
        unset($post['key']);
        if ($post['area']) {
          foreach ($post['area'] as $k => $v) {
            $post['area'][$k] = array_unique($v);
          }
        }
        if (isset($key) && $key != '') {
          $set['setting'][$key] = $post;
        } else {
          $set['setting'][] = $post;
        }
        Bl_Config::set('shipping.'.$name, $set);
        Bl_Config::save();
      } else {
        setMessage('区域不能为空', 'error');
        gotourl('admin/site/editshippingarea/'.$name);
      }
      gotourl('admin/site/listshippingarea/'.$name);
    } else {
      $shippingInstance = Bl_Plugin::getInstance('shipping', $name);
      $config = Bl_Config::get('shipping.'.$name, 0);
      isset($key) ? $config = $config['setting'][$key] : $config = null;
      $form = $shippingInstance->config($config);
      if (isset($config['area']) && $config['area']) {
        foreach($config['area'] as $k => $v) {
          foreach($v as $k2 => $v2) {
            $pids[$k . $v2] = $k . $v2;
          }
        }
      }

      $countries = $this->_siteInstance->getCountries();
      $provinces = $this->_siteInstance->getProvinces(key($countries));
      $this->view->render('admin/site/shippingareainfo.phtml', array(
        'config' => isset($config) ? $config : null,
        'form' => isset($form) ? $form : null,
        'name' => $name,
        'key' => $key,
        'pids' => isset($pids) ? $pids : null,
        'countries' => $countries,
        'provinces' => $provinces
      ));
    }

  }

  public function ajaxgetshippingareaformAction($name, $calculateway, $key=null )
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    $shippingInstance = Bl_Plugin::getInstance('shipping', $name);
    $config = Bl_Config::get('shipping.'.$name, 0);
    isset($key) ? $config = $config['setting'][$key] : $config = null;
    $config['calculateway'] = $calculateway;
    $form = $shippingInstance->config($config);
    echo $form;
  }

  public function deleteshippingareaAction($name, $key)
  {
    if (!access('manage shipping')) {
      goto403('Access Denied.');
    }
    $set = Bl_Config::get('shipping.'.$name, 0);
    unset($set['setting'][$key]);
    Bl_Config::set('shipping.'.$name, $set);
    if (Bl_Config::save()) {
        setMessage('保存成功');
    }
    gotourl('admin/site/listshippingarea/'.$name);
  }

  public function ajaxgetprovinceAction($cid)
  {
    $provinces = $this->_siteInstance->getProvinces($cid);
    echo json_encode($provinces);
  }

  public function paymentlistAction()
  {
    if (!access('manage payment')) {
      goto403('Access Denied.');
    }
    $paymentInstance = Payment_Model::getInstance();
    $this->view->render('admin/site/paymentslist.phtml', array(
      'paymentsList' => $paymentInstance->getPaymentsList(),
    ));
  }

  public function paymentinstallAction($payment)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $paymentInstance = Payment_Model::getInstance();
    if ($instance = $paymentInstance->getPaymentInstance($payment)) {
      $paymentInstance->editPayment($payment, array('status' => true, 'visible' => true));
      $instance->install();
    } else {
      setMessage('Payment not found.');
    }
    gotoUrl('admin/site/paymentlist');
  }

  public function paymentuninstallAction($payment)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $paymentInstance = Payment_Model::getInstance();
    if ($instance = $paymentInstance->getPaymentInstance($payment)) {
      $paymentInstance->editPayment($payment, array('status' => false));
      $instance->uninstall();
    } else {
      setMessage('Payment not found.');
    }
    gotoUrl('admin/site/paymentlist');
  }

  public function paymentshowAction($payment)
  {
    if (!access('manage payment')) {
      goto403('Access Denied.');
    }
    $paymentInstance = Payment_Model::getInstance();
    if ($instance = $paymentInstance->getPaymentInstance($payment)) {
      $paymentInstance->editPayment($payment, array('visible' => true));
    } else {
      setMessage('Payment not found.');
    }
    gotoUrl('admin/site/paymentlist');
  }

  public function paymenthideAction($payment)
  {
    if (!access('manage payment')) {
      goto403('Access Denied.');
    }
    $paymentInstance = Payment_Model::getInstance();
    if ($instance = $paymentInstance->getPaymentInstance($payment)) {
      $paymentInstance->editPayment($payment, array('visible' => false));
    } else {
      setMessage('Payment not found.');
    }
    gotoUrl('admin/site/paymentlist');
  }

  public function paymenteditAction($payment)
  {
    if (!access('manage payment')) {
      goto403('Access Denied.');
    }
    $paymentInstance = Payment_Model::getInstance();
    $paymentInfo = $paymentInstance->getPaymentInfo($payment);
    if (!$paymentInfo || !$paymentInfo->status || !$instance = $paymentInstance->getPaymentInstance($payment)) {
      setMessage('Payment not found.');
      gotoUrl('admin/site/paymentlist');
    }
    $fields = array(
      'name' => '支付方式名称',
      'description' => array(
        'name' => '支付方式描述',
        'type' => 'textarea',
      ),
      'order_description' => array(
        'name' => '下单成功描述',
        'type' => 'textarea',
      ),
      'weight' => '排序',
      'bankfee' => '手续费(可以填写固定金额，也可以填写商品金额的百分比)',
      'isdisplaybutton' => array(
        'name' => '是否显示支付按钮（只有在线支付才有用）',
        'type' => 'select',
        'options' => array(
          '1' => '显示',
          '0' => '隐藏',
        ),
        'default' => '1',
      ),
    );
    $fields = array_merge($fields, $instance->getSettingFields());
    if ($this->isPost()) {
      $post = array();
      foreach ($fields as $field => $row) {
        if (isset($_POST[$field])) {
          $post[$field] = (trim($_POST[$field]) === '') ? null : $_POST[$field];
        }
      }
      if (!empty($post)) {
        $paymentInstance->editPayment($payment, $post);
        setMessage('Payment settings had been saved.');
      }
      gotoUrl('admin/site/paymentlist');
    }
    $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
    $this->view->render('admin/site/paymentinfo.phtml', array(
      'payment' => $paymentInfo,
      'settingFields' => $fields,
    ));
  }

  public function widgetlistAction()
  {
    if (!access('manage widget')) {
      goto403('Access Denied.');
    }
    $widgetInstance = Widget_Model::getInstance();
    $this->view->render('admin/site/widgetslist.phtml', array(
      'widgetsList' => $widgetInstance->getWidgetsList(),
    ));
  }

  public function widgetinstallAction($widget)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $widgetInstance = Widget_Model::getInstance();
    if ($instance = $widgetInstance->getWidgetInstance($widget)) {
      if (method_exists($instance, 'install')) {
        $instance->install();
      }
      $widgetInstance->editWidget($widget, array('status' => true));
    } else {
      setMessage('Widget not found.');
    }
    gotoUrl('admin/site/widgetlist');
  }

  public function widgetuninstallAction($widget)
  {
    if (!access('super')) {
      goto403('Access Denied.');
    }
    $widgetInstance = Widget_Model::getInstance();
    if ($instance = $widgetInstance->getWidgetInstance($widget)) {
      if (method_exists($instance, 'uninstall')) {
        $instance->uninstall();
      }
      $widgetInstance->editWidget($widget, array('status' => false));
    } else {
      setMessage('Widget not found.');
    }
    gotoUrl('admin/site/widgetlist');
  }

  public function widgeteditAction($widget)
  {
    if (!access('manage widget')) {
      goto403('Access Denied.');
    }
    $widgetInstance = Widget_Model::getInstance();
    $widgetInfo = $widgetInstance->getWidgetInfo($widget);
    if (!$widgetInfo || !$widgetInfo->status || !$instance = $widgetInstance->getWidgetInstance($widget)) {
      setMessage('Widget not found.');
      gotoUrl('admin/site/widgetlist');
    }
    $args = func_get_args();
    if (count($args) >= 2 && method_exists($instance, $args[1])) {
      $function = $args[1];
      unset($args[0], $args[1]);
      array_unshift($args, $this, $widgetInfo);
    } else {
      $function = 'editWidget';
      $args[0] = $widgetInfo;
      array_unshift($args, $this);
      if ($this->isPost()) {
      	$function = 'editWidgetPost';
        call_user_func_array(array($instance, $function), $args);
        setMessage('Widget settings had been saved.');
        gotoUrl('admin/site/widgetlist');
      }
    }
    $return = call_user_func_array(array($instance, $function), $args);
    if (false === $return) {
      setMessage('This Widget does not have to set.');
      gotoUrl('admin/site/widgetlist');
    }

  }

  public function siteinfosettingAction(){
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $siteInfo = array(
        'sitename' => $_POST['sitename'],
        'logo' => $_POST['logo'],
        'email' => $_POST['email'],
        'siteurl' => $_POST['siteurl'],
        'record' => $_POST['record'],
        'copyright' => $_POST['copyright'],
      );
      Bl_Config::set('siteInfo',$siteInfo);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/siteinfosetting');
    } else {
      $siteInfo = Bl_Config::get('siteInfo', array());
      $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
      $this->view->render('admin/site/siteinfosetting.phtml', array(
        'siteInfo' => $siteInfo,
      ));
    }
  }

  public function contactwaysettingAction()
  {
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $contactWay = array(
        'companyName' => $_POST['companyName'],
        'companyAddress' => $_POST['companyAddress'],
        'email' => $_POST['email'],
        'telephone' => $_POST['telephone'],
        'im' => $_POST['im'],
      );
      Bl_Config::set('contactWay',$contactWay);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/contactwaysetting');
    } else {
      $contactWay = Bl_Config::get('contactWay', array());
      $this->view->render('admin/site/contactwaysetting.phtml', array(
        'contactWay' => $contactWay,
      ));
    }
  }

  public function displaysettingAction(){
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $displayInfo = array(
        'timeZone' => $_POST['timeZone'],
        'timeFormat' => $_POST['timeFormat'],
        'goodsListNum' => intval($_POST['goodsListNum']) ? intval($_POST['goodsListNum']) : 20,
        'commentListNum' => intval($_POST['commentListNum']) ? intval($_POST['commentListNum']) : 20,
        'articleListNum' => intval($_POST['articleListNum']) ? intval($_POST['articleListNum']) : 20,
        'productListMothed' => $_POST['productListMothed'],
        'productListHomeName' => $_POST['productListHomeName'],
      );
      Bl_Config::set('display',$displayInfo);
      Bl_Config::save();
      setMessage('设置成功');
      unset($_SESSION['browseListConfig']); //清除分页SESSION
      gotourl('admin/site/displaysetting');
    } else {
      $displayInfo = Bl_Config::get('display', array());
      $this->view->render('admin/site/displaysetting.phtml', array(
        'displayInfo' => $displayInfo,
      ));
    }
  }

  public function marketpricesettingAction(){
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $marketprice = array(
        'marketprice' => $_POST['marketprice'],
      );
      Bl_Config::set('marketprice',$marketprice);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/marketpricesetting');
    } else {
      $marketprice = Bl_Config::get('marketprice', array());
      $this->view->render('admin/site/marketpricesetting.phtml', array(
        'marketprice' => $marketprice,
      ));
    }
  }

  public function stmpsettingAction(){
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $stmpInfo = array(
        'stmptype' => $_POST['stmptype'],
        'stmpserver' => $_POST['stmpserver'],
        'stmpport' => $_POST['stmpport'],
        'stmpuser' => $_POST['stmpuser'],
        'stmppasswd' => $_POST['stmppasswd'],
        'mailfrom' => $_POST['mailfrom'],
        'mailfromname' => $_POST['mailfromname'],
        'mailreply' => $_POST['mailreply'],
        'mailreplyname' => $_POST['mailreplyname'],
      );
      Bl_Config::set('stmp',$stmpInfo);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/stmpsetting');
    } else {
      $stmpInfo = Bl_Config::get('stmp', array());
      $this->view->render('admin/site/stmpsetting.phtml', array(
        'stmpInfo' => $stmpInfo,
      ));
    }
  }

  public function integralsettingAction()
  {
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $integralInfo = array(
        'settlementRate' => $_POST['settlementRate'],
        'exchangeRate' => $_POST['exchangeRate'],
      );
      Bl_Config::set('integral',$integralInfo);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/integralsetting');
    } else {
      $integralInfo = Bl_Config::get('integral', array());
      $this->view->render('admin/site/integralsetting.phtml', array(
        'integralInfo' => $integralInfo,
      ));
    }
  }

  public function stockChecksettingAction(){
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $stockCheck = $_POST['status'];
      Bl_Config::set('stockCheck',$stockCheck);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/stockchecksetting');
    } else {
      $stockCheck = Bl_Config::get('stockCheck', false);
      $this->view->render('admin/site/stockchecksetting.phtml', array(
        'stockCheck' => $stockCheck,
      ));
    }
  }

  public function getadphotoListAction($page = 1)
  {
    if (!access('manage adphoto')) {
      goto403('Access Denied.');
    }
    $pageRows = 8;
    $adphotoList = $this->_siteInstance->getadphotoList($page, $pageRows);
    $adphotoCount = $this->_siteInstance->getadphotoCount();
    $this->view->render('admin/site/adphotolist.phtml', array(
      'adphotoList' => isset($adphotoList) ? $adphotoList : array(),
      'pagination' => pagination('admin/site/getadphotolist/%d', $adphotoCount, $pageRows, $page),
      ));
  }

  public function editadphotoAction($aid = NULL)
  {
    if (!access('manage adphoto')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $post = $_POST;
      $fileModel = File_Model::getInstance();
      if (is_numeric($post['script_id'])) {
        setMessage('广告标识必须为字符型', 'error');
        $reffer_url = $_SERVER["HTTP_REFERER"];
        header("Location: ".$reffer_url);exit;
      }
      $post['tid'] = $post['directory_tid4'] ? $post['directory_tid4'] : (
          $post['directory_tid3'] ? $post['directory_tid3'] : (
            $post['directory_tid2'] ? $post['directory_tid2'] : (
              $post['directory_tid1'] ? $post['directory_tid1'] : 0
            )
          )
        );

      if ($_FILES['filedata']['name']) {
        $filepost = array('type' => 'adphoto');
        $file = $fileModel->insertFile('filedata', $filepost);
        $post['fid'] = isset($file->fid) ? $file->fid : '';
        $post['filepath'] = isset($file->filepath) ? $file->filepath : '';
      } else {
        if ($post['filepath'] && $post['fid']) {
          $fileModel->deleteFile($post['fid']);
          $post['fid'] = 0;
        }
      }
      if ($post['aid']) {
        $this->_siteInstance->updateadphoto($post['aid'], $post);
      } else {
        $this->_siteInstance->insertadphoto($post);
      }
      gotoUrl('admin/site/getadphotoList');
    } else {
      $taxonomyInstance = Taxonomy_Model::getInstance();
      $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);

      $adphotoInfo = $this->_siteInstance->getadphotoInfo($aid);

      $taxonomyInstance = Taxonomy_Model::getInstance();
      if (isset($adphotoInfo->tid) && $adphotoInfo->tid) {
       $termInfo = $taxonomyInstance->getTermInfo($adphotoInfo->tid);
       if (isset($termInfo) && $termInfo) {
          if (!$termInfo->ptid1) {
            $adphotoInfo->directory_tid1 = $termInfo->tid;
          } else if (!$termInfo->ptid2) {
            $adphotoInfo->directory_tid1 = $termInfo->ptid1;
            $adphotoInfo->directory_tid2 = $termInfo->tid;
          } else if (!$termInfo->ptid3) {
            $adphotoInfo->directory_tid1 = $termInfo->ptid1;
            $adphotoInfo->directory_tid2 = $termInfo->ptid2;
            $adphotoInfo->directory_tid3 = $termInfo->tid;
          } else {
            $adphotoInfo->directory_tid1 = $termInfo->ptid1;
            $adphotoInfo->directory_tid2 = $termInfo->ptid2;
            $adphotoInfo->directory_tid3 = $termInfo->ptid3;
            $adphotoInfo->directory_tid4 = $termInfo->tid;
          }
        }
      }

      $this->view->render('admin/site/adphotoinfo.phtml', array(
        'adphotoInfo' => isset($adphotoInfo) ? $adphotoInfo : array(),
        'directoryList' => isset($directoryList) ? $directoryList : array(),
        ));
    }
  }

  public function deleteadphotoAction ($aid)
  {
    if (!access('manage adphoto')) {
      goto403('Access Denied.');
    }
    if ($adphotoInfo = $this->_siteInstance->getadphotoInfo($aid)) {
      if ($this->_siteInstance->deleteadphoto($aid)) {
        if ($adphotoInfo->fid) {
          $fileModel = File_Model::getInstance();
          $fileModel->deleteFile($adphotoInfo->fid);
        }
        setMessage('删除成功');
      } else {
        setMessage('删除错误', 'error');
      }
    } else {
      setMessage('cannot found this adphoto!', 'error');
    }
    gotoUrl('admin/site/getadphotoList');
  }

  public function getcarouselphotoListAction($page = 1)
  {
    if (!access('manage carousel photo')) {
      goto403('Access Denied.');
    }
    $pageRows = 10;
    $carouselphotoList = $this->_siteInstance->getcarouselphotoList($page, $pageRows, false);
    $carouselphotoCount = $this->_siteInstance->getcarouselphotoCount(false);
    $this->view->render('admin/site/carouselphotolist.phtml', array(
      'carouselphotoList' => isset($carouselphotoList) ? $carouselphotoList : array(),
      'pagination' => pagination('admin/site/getcarouselphotolist/%d', $carouselphotoCount, $pageRows, $page),
      ));
  }

  public function editcarouselphotoAction ($sid = 0)
  {
    if (!access('manage carousel photo')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $post = $_POST;
      $fileModel = File_Model::getInstance();
      if ($_FILES['filedata']['name']) {
        $filepost = array('type' => 'carouselphoto');
        $file = $fileModel->insertFile('filedata', $filepost);
        $post['fid'] = isset($file->fid) ? $file->fid : '';
        $post['filepath'] = isset($file->filepath) ? $file->filepath : '';
      } else {
        if ($post['filepath'] && $post['fid']) {
          $fileModel->deleteFile($post['fid']);
          $post['fid'] = 0;
        }
      }
      if ($post['sid']) {
        $this->_siteInstance->updatecarouselphoto($post['sid'], $post);
      } else {
        $this->_siteInstance->insertcarouselphoto($post);
      }
      gotoUrl('admin/site/getcarouselphotoList');
    } else {
      $carouselphotoInfo = $this->_siteInstance->getcarouselphotoInfo($sid);
      $this->view->render('admin/site/carouselphotoinfo.phtml', array(
        'carouselphotoInfo' => isset($carouselphotoInfo) ? $carouselphotoInfo : array(),
      ));
    }
  }

  public function deletecarouselphotoAction ($sid)
  {
    if (!access('manage carousel photo')) {
      goto403('Access Denied.');
    }
    if ($carouselphotoInfo = $this->_siteInstance->getcarouselphotoInfo($sid)) {
      if ($this->_siteInstance->deletecarouselphoto($sid)) {
        if ($carouselphotoInfo->fid) {
          $fileModel = File_Model::getInstance();
          $fileModel->deleteFile($carouselphotoInfo->fid);
        }
        setMessage('删除成功');
      } else {
        setMessage('删除错误', 'error');
      }
    } else {
      setMessage('cannot found this photo!', 'error');
    }
    gotoUrl('admin/site/getcarouselphotoList');
  }

  public function mailsettingAction($settingName = null)
  {
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $_POST['status'] = isset($_POST['status']) ? $_POST['status'] : 0;
      if (!isset($settingName)) {
        $settingName = isset($_POST['settingName']) ? $_POST['settingName'] : 'userRegisterEmail';
      }
      $emailSetting = array(
        'status' => $_POST['status'],
        'title' => $_POST['title'],
        'content' => $_POST['content'],
        'type' => $_POST['type'],
        'ccadmin' => $_POST['ccadmin'],
      );
      Bl_Config::set($settingName, $emailSetting);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/mailsetting/' . $settingName);
    } else {
      if (!isset($settingName)) {
        $settingName = 'userRegisterEmail';
      }
      $emailSetting = Bl_Config::get($settingName, array(
        'status' => 0,
        'title' => '',
        'content' => '',
        'type' => 'html',
      	'ccadmin' => 0,
      ));
      $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
      $this->view->render('admin/site/mailsetting.phtml', array(
        'settingName' => $settingName,
        'emailSetting' => $emailSetting,
      ));
    }
  }

  public function blacklistsettingAction()
  {
    if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $ipbanFile = SITESPATH . '/default/plugins/ipban.txt';
      if (isset($_POST['import']) && is_file($ipbanFile)) {
        $_POST['list'] = file_get_contents($ipbanFile);
      }
      $blackList = explode("\n", $_POST['list']);
      $result = array();
      foreach ($blackList as $row) {
        if (preg_match('/^[\d\.]+(?:\/\d+)?$/', trim($row))) {
          $result[] = trim($row);
        }
      }
      Bl_Config::set('black_list', $result);
      Bl_Config::save();
      setMessage('黑名单设置成功');
      gotoUrl('admin/site/blacklistsetting');
    }
    $blackList = Bl_Config::get('black_list', array());
    $this->view->render('admin/site/blacklistsetting.phtml', array(
      'list' => $blackList,
    ));
  }

	public function attachmentsettingAction()
	{
		if (!access('setting')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()){
      $attachment = $_POST['attachment'];
      Bl_Config::set('attachment.setting',$attachment);
      Bl_Config::save();
      setMessage('设置成功');
      gotourl('admin/site/attachmentsetting');
    } else {
      $attachment = Bl_Config::get('attachment.setting', array());
      $this->view->render('admin/site/attachmentsetting.phtml', array(
        'attachment' => $attachment,
      ));
    }
	}

  public function robotssettingAction()
  {
    if (!access('manage robots')) {
      goto403('Access Denied.');
    }
    if ($this->isPost()) {
      $robots = isset($_POST['robots']) ? trim($_POST['robots']) : '';
      Bl_Config::set('robots.txt', $robots);
      Bl_Config::save();
      setMessage('Robots设置成功');
      gotoUrl('admin/site/robotssetting');
    }
    $robots = Bl_Config::get('robots.txt');
    if (!isset($robots)) {
      $robots = file_get_contents(SITESPATH . '/default/plugins/robots.txt');
    }
    $this->view->render('admin/site/robotssetting.phtml', array(
      'robots' => $robots,
    ));
  }

  public function updateAction()
  {
    if (!access('manage update')) {
      goto403('Access Denied.');
    }
    $currentVersion = Bl_Config::get('update.version', 0);
    $updateVersions = $this->_siteInstance->getUpdateVersions($currentVersion);
    if ($this->isPost()) {
      $currentVersion = $this->_siteInstance->runUpdate($currentVersion);
      Bl_Config::set('update.version', $currentVersion);
      Bl_Config::save();
      gotoUrl('admin/site/update');
    }
    $this->view->render('admin/site/update.phtml', array(
      'updateVersions' => $updateVersions,
      'currentVersion' => $currentVersion,
    ));
  }

  public function cleancacheAction()
  {
    cache::clean();
    setMessage('All cache had been cleaned.');
    gotoBack('admin');
  }

  public function pagevariablesthemeAction()
  {
    if (!access('pagevariablestheme')) {
      goto403('Access Denied.');
    }
    $pageVariablesInstance = PageVariable_Model::getInstance();
    $pageVariablesList = $pageVariablesInstance->getPageVariablesThemeList();
    $this->view->render('admin/site/pagevariablesthemelist.phtml', array(
     'pageVariablesList' => $pageVariablesList,
    ));
  }

  public function editPageVariablesAction($pvid = null)
  {
    if (!access('pagevariablestheme')) {
      goto403('Access Denied.');
    }
    $pageVariablesInstance = PageVariable_Model::getInstance();
    if ($this->isPost()) {
      $post = $_POST;
      if (!$post['key']) {
        setMessage('页面模板KEY不能为空', 'error');
        $reffer_url = $_SERVER["HTTP_REFERER"];
        header("Location: ".$reffer_url);exit;
      }
      $post['key'] = strtolower($post['key']);
      if ($post['pvid']) {
        $affctedRows = $pageVariablesInstance->updatePageVariablesByKey($post['key'], $post);
        if ($affctedRows) {
          setMessage('修改页面模板信息成功');
        } elseif($affctedRows == 0) {
          setMessage('没有任何更改');
        }else {
          setMessage('修改页面模板信息失败', 'error');
        }
      } else {
        if ($pageVariablesInstance->getPageVariableByKey($post['key'])) {
          setMessage('KEY存在重复', 'error');
          $reffer_url = $_SERVER["HTTP_REFERER"];
          header("Location: ".$reffer_url);exit;
        }
        if ($pageVariablesInstance->insertPageVariables($post)) {
          setMessage('新增页面模板信息成功');
        } else {
          setMessage('新增页面模板信息失败', 'error');
        }
      }
      gotoUrl('admin/site/pagevariablestheme');
    } else {
      if (isset($pvid)) {
        $pv = $pageVariablesInstance->getPageVariable($pvid);
      }
      $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
      $this->view->render('admin/site/pagevariablesthemeinfo.phtml', array(
       'pv' => isset($pv) ? $pv : null,
      ));
    }
  }

  public function deletePageVariablesAction($pid)
  {
    if (!access('pagevariablestheme')) {
      goto403('Access Denied.');
    }
    $pageVariablesInstance = PageVariable_Model::getInstance();
    if ($pageVariablesInstance->deletePageVariable($pid)) {
      setMessage('删除页面信息模板成功');
    } else {
      setMessage('删除页面信息模板失败', 'error');
    }
    gotoUrl('admin/site/pagevariablestheme');
  }

  public function ajaxGetPageVariablesByKeyAction($key, $type, $id)
  {
    $pageVariablesInstance = PageVariable_Model::getInstance();
    $pageVariablesInfo = $pageVariablesInstance->getPageVariableByKey($key);
    switch ($type) {
      case 'product':
        $dataInstance = Product_Model::getInstance();
        $datainfo = $dataInstance->getProductInfo($id);
        ;
      break;
      case 'term':
        $dataInstance = Taxonomy_Model::getInstance();
        $datainfo = $dataInstance->getTermInfo($id);
        ;
      break;
      case 'article':
        $dataInstance = Content_Model::getInstance();
        $datainfo = $dataInstance->getArticleInfo($id);
        ;
      break;
      case 'page':
        $dataInstance = Content_Model::getInstance();
        $datainfo = $dataInstance->getPageInfo($id);
        ;
      break;
      case 'promotion':
        $dataInstance = Product_Model::getInstance();
        $datainfo = $dataInstance->getPromotionInfo($id);
        ;
      break;
      default:
        $datainfo = null;
        ;
      break;
    }
    $pageVariablesInfo = $pageVariablesInstance->ReplaceThemeVariables($type, $pageVariablesInfo, $datainfo);
    $pageVariablesInfo = $pageVariablesInstance->replaceSiteVariables($pageVariablesInfo);
    echo json_encode($pageVariablesInfo);
  }
}
