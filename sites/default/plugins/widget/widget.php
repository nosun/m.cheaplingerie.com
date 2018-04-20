<?php
abstract class Widget_Abstract
{
  abstract public function editWidget(Bl_Controller $instance, $widgetInfo);

  abstract public function editWidgetPost(Bl_Controller $instance, $widgetInfo);
}
