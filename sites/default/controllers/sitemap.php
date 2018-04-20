<?php
class SiteMap_Controller extends Bl_Controller
{
	public function init()
	{
	}
	
	public function productssearchAction($startString = null)
	{
		$tagList = Taxonomy_Model::getInstance()->getTagListByStartChar(Taxonomy_Model::TYPE_TAG, $startString);
		$this->view->render('seotags.phtml', array('tagStartString' => $startString,
					'tagList' => $tagList));
	}
}