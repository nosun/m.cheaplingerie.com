<?php
class Admin_Terms_Controller extends Bl_Controller
{
  private $_taxonomyModel;

  public static function __permissions()
  {
    return array(
      'list term',
      'edit term',
      'delete term',
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_taxonomyModel = Taxonomy_Model::getInstance();
  }

  public function indexAction()
  {
    gotoUrl('admin/vocabulary');
  }

  public function getListAction($vid)
  {
    if (!access('list term')) {
      goto403('Do not have access');
    }
    if ($vid == 'brand') {
    	$vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid(Taxonomy_Model::TYPE_BRAND);
    } else if ($vid == 'tag') {
      $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid(Taxonomy_Model::TYPE_TAG);
    } else if ($vid == 'directory') {
      $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid(Taxonomy_Model::TYPE_DIRECTORY);
    } else {
      $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid($vid);
    }
    if (!$vocabularyInfo) {
      goto404('Vocabulary not found.');
    } else {
    	$vid = $vocabularyInfo->vid;
      $vocabularyType = $this->_taxonomyModel->vocabularyType;
      if (isset($vocabularyType[$vocabularyInfo->type])) {
        $stype = $vocabularyType[$vocabularyInfo->type];
      } else {
        $stype = $this->getAliasRealize($vocabularyInfo->name);
      }
      $termsList = $this->_taxonomyModel->getTermsList($vid);
      if ($vocabularyInfo->vid == Taxonomy_Model::TYPE_TAG) {
          $tagGroupList = array();
          foreach ($termsList as $tid => $term) {
              if (!key_exists($term->name_cn, $tagGroupList)) {
                  $tagGroupList[$term->name_cn] = array();
              }
              $tagGroupList[$term->name_cn][] = $term;
          }
          $this->view->render('admin/term/taglist.phtml', array(
            'vid' => $vid,
            'stype' => $stype,
            'tagGroupList' => $tagGroupList,
            'vocabularyInfo' => $vocabularyInfo,
          ));
      } else {
          $this->view->render('admin/term/list.phtml', array(
            'vid' => $vid,
            'stype' => $stype,
            'termsList' => $termsList,
            'vocabularyInfo' => $vocabularyInfo,
          ));
      }
    }
  }
  
  public function getInfoAction($vid, $tid = 0, $parent = null)
  {
    if (!access('list term')) {
      goto403('Do not have access');
    }
  	$vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid($vid);
    if (!$vocabularyInfo) {
      goto404('Vocabulary not found.');
    }
    $pageVariable = PageVariable_Model::getInstance();
    $vocabularyType = $this->_taxonomyModel->vocabularyType;
    if (isset($vocabularyType[$vocabularyInfo->type])) {
      $stype = $vocabularyType[$vocabularyInfo->type];
    } else {
      $stype = $this->getAliasRealize($vocabularyInfo->name);
    }
    if (!empty($tid)) {
      $termInfo = $this->_taxonomyModel->getTermInfo($tid,$vid);
      if (!$termInfo->tid) {
        goto404('Term not found.');
      }
      if (isset($termInfo->pvid)) {
      	$pageVariable = PageVariable_Model::getInstance();
      	$termInfo->pv = $pageVariable->selectPageVariables($termInfo->pvid, 'term', $termInfo);
      }
      $array = $this->_taxonomyModel->getTermParents($tid);
      isset($array[0]) ? $array[0] : $array[0] = 0;
      isset($array[1]) ? list($sid1, $sid2) = array($array[1], $array[0]) : list($sid1, $sid2) = array($array[0], 0);
    } else if (!empty($parent)) {
      $termInfo = $this->_taxonomyModel->getTermInfo($parent);
      $termInfo2 = $termInfo;
      unset($termInfo);
      if ($termInfo2) {
        if (!$termInfo2->ptid1) {
          $termInfo->ptid1 = $termInfo2->tid;
        } else if (!$termInfo2->ptid2) {
          $termInfo->ptid1 = $termInfo2->ptid1;
          $termInfo->ptid2 = $termInfo2->tid;
        } else if (!$termInfo2->ptid3) {
          $termInfo->ptid1 = $termInfo2->ptid1;
          $termInfo->ptid2 = $termInfo2->ptid2;
          $termInfo->ptid3 = $termInfo2->tid;
        }
      }
    }
    $filterGroupList = Filtergroup_Model::getInstance()->getFilterGroupList();
    $this->view->assign('filterGroupList', $filterGroupList);
    
    if (isset($termInfo) && isset($termInfo->tid))
    {
        $filterGroup = Filtergroup_Model::getInstance()->getTermsFilterGroup($termInfo->tid);
        if (!empty($filterGroup->fid))
        {
            $termInfo->fid = $filterGroup->fid;
        }
    }
    $pvThemes = $pageVariable->getPageVariablesThemeList();
    $this->view->assign('pvThemes', $pvThemes);
    $termsList = $this->_taxonomyModel->getTermsList($vid);
    
    $this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
    $this->view->render('admin/term/info.phtml',array(
      'vid' => $vid,
      'stype' => $stype,
      'termsList' => isset($termsList) ? $termsList : array(),
      'termInfo' => isset($termInfo) ? $termInfo : null,
      'vocabularyInfo' => $vocabularyInfo,
      'pv' => isset($termInfo->pv) ? $termInfo->pv : null,
    ));
  }

  public function saveAction($vid)
  {
    if (!access('edit term')) {
      goto403('Do not have access');
    }
    if ($this->isPost()) {
	    if (!access('edit term')) {
	      goto403('Do not have access');
	    }
	    $post = $_POST;
    	$post['name'] = trim($post['name']);
    	$post['tid'] = isset($post['tid']) ? $post['tid']: 0;
      $post['sclass1'] = isset($post['sclass1']) ? $post['sclass1']: 0;
      $post['sclass2'] = isset($post['sclass2']) ? $post['sclass2']: 0;
      $post['sclass3'] = isset($post['sclass3']) ? $post['sclass3']: 0;
      $pageVariable = PageVariable_Model::getInstance();
      $post['parent'] = $post['sclass3'] ? $post['sclass3'] : (
            $post['sclass2'] ? $post['sclass2'] : (
              $post['sclass1'] ? $post['sclass1'] : 0
            )
          );
      //判断分类是否有效
      if (!$post['name']) {
      	setMessage('分类名称不能为空', 'error');
      	gotoUrl('admin/terms/getInfo/' . $vid . '/' . $post['tid']);
      }
      $post['parents'] = array();
      $post['sclass3'] ? $post['parents'][] = $post['sclass3'] : null;
      $post['sclass2'] ? $post['parents'][] = $post['sclass2'] : null;
      $post['sclass1'] ? $post['parents'][] = $post['sclass1'] : null;
      if (in_array($post['tid'], $post['parents'])) {
        setMessage('分类关系错误', 'error');
        gotoUrl('admin/terms/getInfo/' . $vid . '/' . $post['tid']);
      }
      //判断是否超过3级分类
      if (!empty($post['tid'])) {echo $post['parent'];
        $grade = $this->_taxonomyModel->getTermGrade($post['tid'], $post['parent']);
        if ($grade > 4) {
          setMessage('分类等级不能超过4级(包括上级分类和下级分类等级数)', 'error');
          gotoUrl('admin/terms/getInfo/' . $vid . '/' . $post['tid']);
        }
      }
      if (empty($post['path_alias'])) {
        $post['path_alias'] = $this->getAlias($post['name'], $post['stype'], $post['sclass1'], $post['sclass2']);
      }
      $parent = $post['parent'] ? $post['parent'] : null;
      $termInfo = $this->_taxonomyModel->getTermInfoByName($post['name'], $post['vid'], $parent);
      
      if (isset($termInfo) && $termInfo) {
        $termInfo->parent = $this->_taxonomyModel->getTermParentByTid($termInfo->tid);
      }
      if (!isset($termInfo) || !$termInfo || $post['tid'] == $termInfo->tid || ($termInfo->parent != $post['parent'])) {
      	if (isset($_FILES['filedata'])) {
        	$fileModel = File_Model::getInstance();
		      $filepost = array('type' => 'terms');
		      $file = $fileModel->insertFile('filedata', $filepost);
		      $post['fid'] = isset($file->fid) ? $file->fid : '';
		      $post['filepath'] = isset($file->filepath) ? $file->filepath : '';
        }
        if (isset($post['pvTheme']) && $post['pvTheme']) {
          $post['pvid'] = $post['pvTheme'];
        } else {
          if ($post['pvid']) {
              $post['pvid'] = $pageVariable->updatePageVariables($post['pvid'], $post);
          } else {
              $pvid = $pageVariable->insertPageVariables($post);
  		        $post['pvid'] = $pvid;
  		    }
        }
        $terms_filter_group = intval($post['term_filter_group']);
        if (!empty($post['tid'])) {
            $post['path_alias'] = $this->_taxonomyModel->getTermPathAlias($post['path_alias'], $post['tid']);
            $result = $this->_taxonomyModel->updateTerm($post['tid'], $post);
            if (!$result) {
              setMessage('修改分类词错误', 'error');
              gotoUrl('admin/terms/getInfo/' . $vid . '/' . $post['tid']);
            } else {
                $filterGroup = Filtergroup_Model::getInstance()->getTermsFilterGroup($post['tid']);
                if (empty($filterGroup->tid)) {
                    $result = Filtergroup_Model::getInstance()->insertTermsFilterGroup($post['tid'], $terms_filter_group);
                    if (!$result) {
                        setMessage('修改分类词成功,但设置过滤选项组失败', 'error');
                    } else {
                        setMessage('修改分类词成功');
                    }
                } else {
                    if ($filterGroup->fid != $terms_filter_group) {
                        $result = Filtergroup_Model::getInstance()->updateTermsFilterGroup($post['tid'], $terms_filter_group);
                        if (!$result) {
                            setMessage('修改分类词成功,但设置过滤选项组失败', 'error');
                        } else {
                            setMessage('修改分类词成功');
                        } 
                    } else {
                        setMessage('修改分类词成功');
                    }
                }
            }
            
        } else {
             $post['path_alias_s'] = $post['path_alias'];
             $post['path_alias'] = randomString(8);
             $result = $new_tid = $this->_taxonomyModel->insertTerm($post);
             if (!$result) {
               setMessage('新增分类词错误', 'error');
               gotoUrl('admin/terms/getInfo/' . $vid . '/' . $post['tid']);
             } else {
               $post['path_alias'] = $this->_taxonomyModel->getTermPathAlias($post['path_alias_s'], $new_tid);
               $this->_taxonomyModel->updateTermPathAlias($new_tid, $post['path_alias'], $post['vid']);
               if ($terms_filter_group != 0) {
                   $result = Filtergroup_Model::getInstance()->insertTermsFilterGrop($new_tid, $terms_filter_group);
                   if ($result) {
                       setMessage('新增分类词成功');
                   } else {
                       setMessage('修改分类词成功,但设置过滤选项组失败', 'error');
                   }
               } else {
                   setMessage('新增分类词成功');
               }
            }
        }
      }else{
          setMessage('分类词重复', 'error');
          gotoUrl('admin/terms/getInfo/' . $vid . '/' . $post['tid']);
      }
      gotoUrl('admin/terms/getlist/'.$vid);
    } else {
      goto404('Forbidden Access is forbidden to the requested page.');
    }
  }

  public function deleteAction($vid, $tid)
  {
    if (!access('delete term')) {
      goto403('Do not have access');
    }
    $vocabularyInfo = $this->_taxonomyModel->getVocabularyInfoByVid($vid);
    if (!$vocabularyInfo) {
      goto404('Vocabulary not found.');
    }
    $termInfo = $this->_taxonomyModel->getTermInfo($tid, $vid);
    if (!$termInfo) {
      goto404('Term not found.');
    }
    $result = $this->_taxonomyModel->deleteTerm($vid, $tid);
    if (!$result) {
      setMessage(' 删除分类词下级分类失败', 'error');
    }
    gotoUrl('admin/terms/getlist/'.$vid);
  }

  public function getAliasAction($str='default', $type='vocabulary', $class1='', $class2='', $class3='')
  {
    $str = str_replace('x0x', '/', $str);
    $class1 = str_replace('x0x', '/', $class1);
    $class2 = str_replace('x0x', '/', $class2);
    $class3 = str_replace('x0x', '/', $class3);
    echo $this->getAlias($str, $type, $class1, $class2, $class3);exit;
  }

  /**
   * 获取分类词的别名
   * @param string $str 分类词名称
   * @param string 分类词所属分类的类型
   * @param string 一级分类的名称
   * @param string 二级分类的名称
   * @return string 英文别名
   */
  public  function getAlias($str, $type, $class1, $class2, $class3){
    $alias = '';
    if ($type != 'directory') {
      $alias .= $type . '-';
    }
    if (!empty($class1)) {
      $alias .= $this->getAliasRealize($class1) . '-';
    }
    if (!empty($class2)) {
      $alias .= $this->getAliasRealize($class2) . '-';
    }
    if (!empty($class3)) {
      $alias .= $this->getAliasRealize($class3) . '-';
    }
    $alias .= $this->getAliasRealize($str) . '-';
    return substr($alias, 0,-1);
  }

  /**
   * 获取中文翻译
   * @param string $str 待翻译词
   * @return string 翻译后的英文
   */
  public function getAliasRealize($str){
    $commonInstance = Common_Model::getInstance();
    $alias = $commonInstance->callFunction('translate', urldecode($str));
    return $alias;
  }
  
  /**
   * 获取特殊分类的商品
   */
  public function listproductAction($vid, $tid)
  {
    $productInstance = Product_Model::getInstance();
    
    if (!access('edit term')) {
      goto403('Do not have access');
    }
    if ($vid != Taxonomy_Model::TYPE_RECOMMEND) {
      goto404('fridden');
    }
    if (!$termInfo = $this->_taxonomyModel->getTermInfo($tid, $vid)) {
      
      goto404('can not found terms');
    }
    
    $productList = $productInstance->getProductsListBySpecial(array('termname' => $termInfo->name));
    $this->view->render('admin/term/listproduct.phtml', array(
      'productList' => isset($productList) ? $productList : array(),
      'tid' => $tid,
      'vid' => $vid,
    ));
    
  }
  
  public function delproductAction($tid, $pids)
  {
    if (!access('edit term')) {
      goto403('Do not have access');
    }
    if ($this->isPost()) {
      $post = $_POST;
      if (isset($post['checkItem']) && !empty($post['checkItem'])) {
        $pids = $post['checkItem'];
      }
      $tid = $post['tid'];
    }
    if (!$termInfo = $this->_taxonomyModel->getTermInfo($tid)) {
      goto404('can not found terms');
    }
    
    if ($this->_taxonomyModel->delTermProducts($tid, $pids)) {
      setMessage('Delete products successful!');
    } else {
      setMessage('error', 'error');
    }
    
    gotoUrl('admin/terms/listproduct/' . $termInfo->vid . '/' . $tid);
  }
}
