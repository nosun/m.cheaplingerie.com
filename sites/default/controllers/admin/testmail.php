<?php
class Admin_TestMail_Controller extends Bl_Controller
{
  public function sendAction()
  {
    $stmpSetting = Bl_Config::get('stmp');
    $emailSetting = Bl_Config::get('orderTradingEmail');
    $mailInstance = new Mail_Model($stmpSetting);
    $email = isset($user->email) ? $user->email : null;
    $mailInstance->sendMail('zhangpeng@mingdabeta.com', $emailSetting['title'], $emailSetting['content'], $emailSetting['type']);
  }
}