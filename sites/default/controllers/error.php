<?php
final class Error_Controller extends Bl_Controller
{
  public function errorAction($ex = null)
  {
    if (isset($ex) && $ex instanceof Exception) {
      if ($ex instanceof Bl_404_Exception) {
        $this->_404($ex);
      } else if ($ex instanceof Bl_403_Exception) {
        $this->_403($ex);
      } else if ($ex instanceof Bl_Db_Exception) {
        $this->_db($ex);
      } else {
        $this->_general($ex);
      }
    } else {
      $this->_404(new Bl_404_Exception('Argument is invalid.'));
    }
  }

  private function _404(Bl_404_Exception $ex)
  {
    callFunction('error_404', $this);
    $this->view->render('error/404.phtml', array(
      'ex' => $ex,
      'debug' => Bl_Config::get('debug', false),
      'timer' => timer(),
    ));
  }

  private function _403(Bl_403_Exception $ex)
  {
    callFunction('error_403', $this);
    $this->view->render('error/403.phtml', array(
      'ex' => $ex,
      'debug' => Bl_Config::get('debug', false),
      'timer' => timer(),
    ));
  }

  private function _db(Bl_Db_Exception $ex)
  {
    callFunction('error_db', $this);
    $this->view->render('error/db.phtml', array(
      'ex' => $ex,
      'debug' => Bl_Config::get('debug', false),
      'timer' => timer(),
    ));
  }

  private function _general(Exception $ex)
  {
    callFunction('error_general', $this);
    $this->view->render('error/general.phtml', array(
      'ex' => $ex,
      'debug' => Bl_Config::get('debug', false),
      'timer' => timer(),
    ));
  }
}
