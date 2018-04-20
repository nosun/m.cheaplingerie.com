<?php
class Widget_Model extends Bl_Model
{
  /**
   * @return Widget_Model
   */
  public static function getInstance()
  {
    return parent::getInstance(__CLASS__);
  }

  /**
   * 获取 Widget 列表
   * @param boolean 是否重新读取
   * @return array
   */
  public function getWidgetsList($reset = false)
  {
    static $list = null;
    if (!isset($list) || $reset) {
      $list = Bl_Plugin::getList('widget', $reset);
      foreach ($list as $pk => &$widget) {
        $settings = Bl_Config::get('widget.' . $pk, array(
          'status' => false,
        ));
        if (is_array($settings)) {
          foreach ($settings as $key => $value) {
            $widget->{$key} = $value;
          }
        } else {
          $widget->settings = $settings;
        }
      }
    }
    return $list;
  }

  /**
   * 获取 Widget 信息
   * @param string $widget Widget标识
   */
  public function getWidgetInfo($widget)
  {
    $widgets = $this->getWidgetsList();
    return isset($widgets[$widget]) ? $widgets[$widget] : false;
  }

  /**
   * 获取 Widget 实例
   * @param object $widget Widget 标识/对象
   * @return Widget_Abstract
   */
  public function getWidgetInstance($widget)
  {
    return Bl_Plugin::getInstance('widget', $widget);
  }

  /**
   * 修改 Widget 设置
   * @param string $widget Widget 标识
   * @param array $post 表单数组
   * @return array
   */
  public function editWidget($widget, $post)
  {
    $settings = Bl_Config::get('widget.' . $widget, array(
      'status' => false,
    ));
    foreach ($post as $key => $value) {
      $settings[$key] = $value;
    }
    Bl_Config::set('widget.' . $widget, $settings);
    Bl_Config::save();
    return $settings;
  }
}
