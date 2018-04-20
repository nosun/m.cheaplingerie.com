<?php
class Widget_Controller extends Bl_Controller
{
  public static function __router($paths)
  {
    if (!isset($paths[1])) {
      goto404(t('Arguments error'));
    }
    $widgetName = array_shift($paths);
    $widgetInstance = Widget_Model::getInstance();
    $widgetInfo = $widgetInstance->getWidgetInfo($widgetName);
    if (!$widgetInfo) {
      goto404(t('Widget not found'));
    }
    $widget = $widgetInstance->getWidgetInstance($widgetName);
    if (!method_exists($widget, 'urls')) {
      goto404(t('Action is invalid'));
    }
    $action = strtolower(array_shift($paths));
    $actionFunction = '_' . $action;
    $urls = $widget->urls();
    if (!in_array($action, $urls) || !method_exists($widget, $actionFunction)) {
      goto404(t('Action is invalid'));
    }
    array_unshift($paths, new self());
    call_user_func_array(array($widget, $actionFunction), $paths);
    exit;
  }
}
