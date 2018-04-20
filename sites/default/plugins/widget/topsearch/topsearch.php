<?php
class Topsearch extends Widget_Abstract
{
  public function install()
  {
    global $db;
    $sql = 'CREATE TABLE IF NOT EXISTS `widget_topsearch` (
              `keyword` VARCHAR(255) NOT NULL,
              `freq` INT NOT NULL DEFAULT 0 ,
              `created` INT UNSIGNED NOT NULL DEFAULT 0 ,
              `updated` INT UNSIGNED NOT NULL DEFAULT 0 ,
              PRIMARY KEY (`keyword`) )
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci';
    $db->exec($sql);
  }

  public function hook_search(Bl_Controller $instance, $keyword)
  {
    $this->addKeywordFreq($keyword);
  }

  public function getResult()
  {
    return explode(PHP_EOL, Bl_Config::get('topsearchword', ''));
  }

  public function editWidget(Bl_Controller $instance, $widgetInfo)
  {
    $topSearchWord = Bl_Config::get('topsearchword', '');
    $instance->view->render('../plugins/widget/topsearch/info.phtml', array(
      'topSearchWord' => $topSearchWord,
      'keywords' => $this->getKeywordList(),
    ));
  }

  public function editWidgetPost(Bl_Controller $instance, $widgetInfo)
  {
    if (isset($_POST['topSearchKeyword'])) {
      $topSearchWord = $_POST['topSearchKeyword'];
      $wordsList = explode(PHP_EOL, $topSearchWord);
      $newKeyWords = '';
      if (is_array($wordsList) && count($wordsList)>0) {
        foreach ($wordsList as $key => $word) {
          $word = trim($word);
          if ($word=='') {
            continue;
          }
          $newKeyWords .= $key == 0 ? $word : PHP_EOL . $word;
        }
      }
      Bl_Config::set('topsearchword', $newKeyWords);
      Bl_Config::save();
    }
    setMessage('Top search keywords had been saved.');
    gotoUrl('admin/site/widgetedit/topsearch');
  }

  private function getKeywordList()
  {
    global $db;
    $result = $db->query('SELECT * FROM `widget_topsearch` ORDER BY `freq` DESC LIMIT 50');
    return $result->allWithKey('keyword');
  }

  public function addKeywordFreq($keyword, $qty = 1)
  {
    global $db;
    $qty = $db->escape($qty);
    $db->exec('UPDATE `widget_topsearch` SET `freq` = `freq` + ' . $qty . ', `updated` = ' . TIMESTAMP .
      ' WHERE `keyword` = "' . $db->escape($keyword) . '"');
    if (!$db->affected()) {
      $db->exec('INSERT INTO `widget_topsearch` (`keyword`, `freq`, `created`, `updated`) VALUES ("' . $db->escape($keyword) .
        '", ' . $qty . ', ' . TIMESTAMP . ', ' . TIMESTAMP . ')');
    }
  }
}