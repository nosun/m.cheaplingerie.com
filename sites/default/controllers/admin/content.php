<?php
class Admin_Content_Controller extends Bl_Controller
{
  private $_contentModel;

  public static function __permissions()
  {
    return array(
      'list article',
      'edit article',
      'delete article',
      'list articletype',
      'edit articletype',
      'delete articletype',
      'list page',
      'edit page',
      'delete page',
    );
  }

  public function init()
  {
    if (!access('administrator page')) {
      goto403('<a href="' . url('admin/login') . '">登录</a>');
    }
    $this->_contentModel = Content_Model::getInstance();
  }

  public function indexAction()
  {
    gotoUrl('admin/content/getarticlelist');
  }

  public function firstListAction($key, $posturl)
  {
    if ($key == 'all') {
      foreach ($_SESSION['listarticle'] as $key1 => $dl) {
        unset($_SESSION['listarticle'][$key1]);
      }
    } else {
      unset($_SESSION['listarticle'][$key]);
    }
    if (!isset($posturl) || !$posturl) {
      $posturl = 'admin/content/getArticleList';
    }
    gotourl(str_replace('@@@', '/', $posturl));
  }

  public function getArticleListAction($page = 1)
  {
    if (!access('list article')) {
      goto403('Do not have access');
    }
  	$pageRows = 20;
    $filter = array();
    if ($this->isPost()) {
      $filter = $_POST;
      $filter['tid'] = $filter['directory_tid4'] ? $filter['directory_tid4'] : (
          $filter['directory_tid3'] ? $filter['directory_tid3'] : (
            $filter['directory_tid2'] ? $filter['directory_tid2'] : (
              $filter['directory_tid1'] ? $filter['directory_tid1'] : 0
            )
          )
        );  
      foreach ($_POST as $key=>$dl) {
        $_SESSION['listarticle'][$key] = $dl;
      }
    } else {
      if(isset($_SESSION['listarticle'])){
          $filter = $_SESSION['listarticle'];
      }
    }
    $articleList = $this->_contentModel->getArticleList($filter, $page, $pageRows);
    $articlecount = $this->_contentModel->getArticleCount($filter);
    $articletypes = $this->_contentModel->getArticleTypeList();
    $selectHtml = $this->_contentModel->getSelectHtml($filter, str_replace('/', '@@@', 'admin/content/getArticleList'));
    
    $taxonomyInstance = Taxonomy_Model::getInstance();
    
    $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
    $this->view->render('admin/content/getarticlelist.phtml', array(
      'articleList' => $articleList,
      'articletypes' => $articletypes,
      'directoryList' => $directoryList,
      'selectHtml' => $selectHtml,
      'pagination' => pagination('admin/content/getarticlelist/%d', $articlecount, $pageRows, $page),
    ));

  }

  public function getArticleInfoAction($aid)
  {
    if (!access('list article')) {
      goto403('Do not have access');
    }
    $pageInstance = PageVariable_Model::getInstance();
  	if ($aid) {
	  	$articleInfo = $this->_contentModel->getArticleInfo($aid);
	  	if (!$articleInfo) {
	  		setMessage('This article can not fount', 'error');
        gotourl('admin/content');
	  	}
	  	if (isset($articleInfo->pvid)) {
	  		//获取页面信息
	  		$articleInfo->pv = $pageInstance->selectPageVariables($articleInfo->pvid, 'article', $articleInfo);
	  		//获取相关文章
        $articleRelatedList = $this->_contentModel->getArticleRelated($articleInfo->aid);
        if (isset($articleRelatedList)) {
		  	  foreach ($articleRelatedList as $k => $d) {
	          $articleRelatedData[$d->related_aid]->aid = $d->related_aid;
	          $articleRelatedData[$d->related_aid]->title = $d->title;
	        }
        }
	  	  $articleProductRelatedList = $this->_contentModel->getArticleProductRelated($articleInfo->aid);
        if (isset($articleProductRelatedList)) {
          foreach ($articleProductRelatedList as $k => $d) {
            $articleProductRelatedData[$d->pid]->pid = $d->pid;
            $articleProductRelatedData[$d->pid]->name = $d->name;
            $articleProductRelatedData[$d->pid]->isbothway = 0;
          }
        }
        //获取相关商品
        $articleProductRelatedList = array();
	  	}
	  	$pvThemes = $pageInstance->getPageVariablesThemeList();
      $this->view->assign('pvThemes', $pvThemes);
	  }
  	$articletypes = $this->_contentModel->getArticleTypeList();
  	$taxonomyInstance = Taxonomy_Model::getInstance();
  	if (isset($articleInfo->tid) && $articleInfo->tid) {
  	 $termInfo = $taxonomyInstance->getTermInfo($articleInfo->tid);
  	 if (isset($termInfo) && $termInfo) {
        if (!$termInfo->ptid1) {
          $articleInfo->directory_tid1 = $termInfo->tid;
        } else if (!$termInfo->ptid2) {
          $articleInfo->directory_tid1 = $termInfo->ptid1;
          $articleInfo->directory_tid2 = $termInfo->tid;
        } else if (!$termInfo->ptid3) {
          $articleInfo->directory_tid1 = $termInfo->ptid1;
          $articleInfo->directory_tid2 = $termInfo->ptid2;
          $articleInfo->directory_tid3 = $termInfo->tid;
        } else {
          $articleInfo->directory_tid1 = $termInfo->ptid1;
          $articleInfo->directory_tid2 = $termInfo->ptid2;
          $articleInfo->directory_tid3 = $termInfo->ptid3;
          $articleInfo->directory_tid4 = $termInfo->tid;
        }
      }
  	}
    $directoryTermsList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY, false);
    $directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
  	
  	$this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
  	$this->view->render('admin/content/getarticleinfo.phtml', array(
      'articleInfo' => isset($articleInfo) ? $articleInfo : null,
      'articletypes' => isset($articletypes) ? $articletypes : null,
  	  'directoryList' => $directoryList,
  	  'directoryTermsList' => $directoryTermsList,
      'articleRelatedData' => isset($articleRelatedData) ? $articleRelatedData : array(),
      'articleProductRelatedData' => isset($articleProductRelatedData) ? $articleProductRelatedData : array(),
      'pv' => isset($articleInfo->pv) ? $articleInfo->pv : null,
    ));
  }

  public function editArticleAction()
  {
    if (!access('edit article')) {
      goto403('Do not have access');
    }
  	if ($this->isPost()) {
  		$post = $_POST;
  		$post['aid'] = isset($post['aid']) ? $post['aid'] : null;
  		$post['article_title'] = trim($post['article_title']);
  	  if (!$post['article_title']) {
        setMessage('文章标题不能为空', 'error');
        gotoUrl('admin/content/getArticleInfo/' . $post['aid']);
      }
  		if ($post['aid'] && !$this->_contentModel->getArticleInfo($post['aid'])) {
        setMessage('This article can not fount', 'error');
        gotourl('admin/content/getArticleList');
      }
  		$post['status'] = isset($post['status']) && $post['status']=='on' ? 1 : 0;
  		$pageInstance = PageVariable_Model::getInstance();
  		if (isset($_POST['pvTheme']) && $_POST['pvTheme']) {
        $post['pvid'] = $_POST['pvTheme'];
      } else {
    	  if ($post['pvid']) {
          $post['pvid'] = $pageInstance->updatePageVariables($post['pvid'], $post);
        } else {
          $pvid = $pageInstance->insertPageVariables($post);
          $post['pvid'] = $pvid;
        }
      }
      $commonInstance = Common_Model::getInstance();
      if (!$post['path_alias']) {
        $post['path_alias'] = $commonInstance->translate(urldecode($post['article_title']));
      } else {
        $post['path_alias'] = $commonInstance->translate(urldecode($post['path_alias']));
      }
      echo $post['articleRelatedData'];echo '<br><br>';
      $post['path_alias'] = $this->_contentModel->getArticlePathAlias($post['path_alias'], $post['aid']);
      $articleRelatedData = str_replace('\\\'', '', $post['articleRelatedData']);
      
      echo $articleRelatedData;echo '<br><br>';
      $articleRelatedData = json_decode(strtr(stripcslashes($articleRelatedData), '\'', '"'));
      $articleProductData = json_decode(strtr(stripcslashes($post['related_info']), '\'', '"'));
      var_dump($articleRelatedData);
      $post['tid'] = $post['directory_tid4'] ? $post['directory_tid4'] : (
          $post['directory_tid3'] ? $post['directory_tid3'] : (
            $post['directory_tid2'] ? $post['directory_tid2'] : (
              $post['directory_tid1'] ? $post['directory_tid1'] : 0
            )
          )
        );
      if ($post['aid']) {
      	if ($this->_contentModel->updateArticle($post['aid'], $post)) {
  				$this->_contentModel->deleteArticleRelated($post['aid']);
  				$this->_contentModel->insertArticleRelated($post['aid'], $articleRelatedData);
  				$this->_contentModel->deleteArticleProductRelated($post['aid']);
          $this->_contentModel->insertArticleProductRelated($post['aid'], $articleProductData);
  				setMessage('文章信息修改成功', 'notice');
  			} else {
  				setMessage('文章信息修改失败', 'error');
  			}
  		} else {
  			$post['aid'] = $this->_contentModel->insertArticle($post);
  			if ($post['aid']) {
  				$this->_contentModel->insertArticleRelated($post['aid'], $articleRelatedData);
          $this->_contentModel->insertArticleProductRelated($post['aid'], $articleProductData);
  				setMessage('新增文章信息成功', 'notice');
  			} else {
  				setMessage('新增文章信息失败', 'error');
  			}
  		}
  	} else {
  		goto404('禁止访问！');
  	}
  	gotourl('admin/content');
  }

  public function postAction(){
  	if ($this->isPost()) {
  		$post = $_POST;
  		if (isset($post['btn_delete'])) {
  		  if (!access('delete article')) {
          goto403('Do not have access');
        }
  			foreach ($post['aids'] as $k => $v) {
  				$this->_contentModel->deleteArticle($v);
  			}
  			setMessage('删除文章成功', 'notice');
  		} elseif (isset($post['btn_show'])) {
  		  if (!access('edit article')) {
          goto403('Do not have access');
        }
  			$set = array('status' => 1);
  			foreach ($post['aids'] as $k => $v) {
          $this->_contentModel->updateArticle($v ,$set, 1);
        }
  			setMessage('设置成功', 'notice');
  		} elseif (isset($post['btn_hidden'])) {
  		  if (!access('edit article')) {
          goto403('Do not have access');
        }
  			$set = array('status' => 0);
  			foreach ($post['aids'] as $k => $v) {
        	$this->_contentModel->updateArticle($v ,$set, 1);
        }
        setMessage('设置成功', 'notice');
  		} elseif (isset($post['btn_change_type'])) {
  		  if (!access('edit article')) {
          goto403('Do not have access');
        }
  			if ($post['new_atid']) {
	        $set = array('atid' => $post['new_atid']);
	        foreach ($post['aids'] as $k => $v) {
	          $this->_contentModel->updateArticle($v ,$set, 1);
	        }
	        setMessage('修改分类成功', 'notice');
  			} else {
  				setMessage('请选择需要转换的分类', 'error');
  			}
      }
    } else {
  		goto404('禁止访问！');
  	}
  	gotourl('admin/content');
  }

  public function deleteArticleAction($aid)
  {
    if (!access('delete article')) {
      goto403('Do not have access');
    }
    if ((boolean)$this->_contentModel->getArticleInfo($aid)) {
      if ($this->_contentModel->deleteArticle($aid)) {
      	setMessage('删除文章成功', 'notice');
      } else {
      	setMessage('删除文章失败', 'error');
      }
    } else {
    	goto404('NO ARTICLE!');
    }
    gotourl('admin/content');
  }

  public function getArticleTypeListAction()
  {
    if (!access('list articletype')) {
      goto403('Do not have access');
    }
  	$articleTypeList = $this->_contentModel->getArticleTypeList();
  	$this->view->render('admin/content/getarticletypelist.phtml', array(
      'articleTypeList' => $articleTypeList,
  	));
  }

  public function editArticleTypeAction($atid = 0, $parent = 0)
  {
    if (!access('edit articletype')) {
      goto403('Do not have access');
    }
    $pageInstance = PageVariable_Model::getInstance();
    
    if ($this->isPost()) {
      $post = $_POST;
      $post['path_alias'] = $this->_contentModel->getArticleTypePathAlias($post['path_alias'], $atid);
      
      if (isset($_POST['pvTheme']) && $_POST['pvTheme']) {
        $post['pvid'] = $_POST['pvTheme'];
      } else {
        if ($post['pvid']) {
          $post['pvid'] = $pageInstance->updatePageVariables($post['pvid'], $post);
        } else {
          $pvid = $pageInstance->insertPageVariables($post);
          $post['pvid'] = $pvid;
        }
      }
      
      if ($post['atid']) {
      	if (!$this->_contentModel->getArticleTypeInfo($post['atid'])) {
      		setMessage('This type can not fount');
          gotoUrl('admin/content/getArticleTypeList');
      	}
      	
      	$result = $this->_contentModel->updateArticleType($post['atid'], $post);
      	if($result == 1) {
      		setMessage('修改成功', 'notice');
      	} elseif ($result == 0) {
      		setMessage('没有任何修改', 'notice');
      	} else {
      		setMessage('修改失败', 'error');
      	}
      } else {
      	$atid = $this->_contentModel->insertArticleType($post);
        if($atid) {
          setMessage('添加文章分类成功', 'notice');
        } else {
          setMessage('添加文章分类失败', 'error');
        }
      }
      gotoUrl('admin/content/getarticletypelist');
    } else {
    	if ($atid) {
	        $articleTypeInfo = $this->_contentModel->getArticleTypeInfo($atid);
	        if ($articleTypeInfo) {
	        	$parent = $articleTypeInfo->parent;
	        } else {
	        	setMessage('This type can not fount');
	        	gotoUrl('admin/content/getarticletypelist');
	        }
	        if($articleTypeInfo->pvid){
	        	$articleTypeInfo->pv = $pageInstance->selectPageVariables($articleTypeInfo->pvid, 'articletype', $articleTypeInfo);
	        }
    	}
    	$articletypes = $this->_contentModel->getArticleTypeList();
    	
    	$this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
    	$this->view->render('admin/content/getarticletypeinfo.phtml', array(
	      'articleTypeInfo' => isset($articleTypeInfo) ? $articleTypeInfo : array(),
	      'articletypes' => isset($articletypes) ? $articletypes : array(),
    	  'parent' => $parent,
    	  'pv' => isset($articleTypeInfo->pv) ? $articleTypeInfo->pv : null,
	    ));
    }
  }

  public function deleteAriticleTypeAction($atid)
  {
    if (!access('delete articletype')) {
      goto403('Do not have access');
    }
    $articleTypeInfo = $this->_contentModel->getArticleTypeInfo($atid);
    if ($articleTypeInfo) {
    	$result = $this->_contentModel->deleteArticleType($atid);
    	if ($result) {
    		setMessage('删除文章分类成功');
    	} else {
    		setMessage('删除文章分类错误', 'error');
    	}
    } else {
    	setMessage('This type can not fount', 'error');
    }
    gotoUrl('admin/content/getarticletypelist');
  }

  public function getAricleRelatedListAction($page = 1 , $isfilter = null)
  {
    if (!access('edit article')) {
      goto403('Do not have access');
    }
    $pageRows = 12;
    $filter = array();
    if ($this->isPost()) {
      $filter = $_POST;
      $isfilter = isset($isfilter) ? $isfilter : 0;  
      $filter['tid'] = $filter['directory_tid4'] ? $filter['directory_tid4'] : (
          $filter['directory_tid3'] ? $filter['directory_tid3'] : (
            $filter['directory_tid2'] ? $filter['directory_tid2'] : (
              $filter['directory_tid1'] ? $filter['directory_tid1'] : 0
            )
          )
        );
      foreach ($filter as $key=>$dl) {
        $_SESSION['listarticle' . $isfilter][$key] = $dl;
      }
    }
    if(isset($_SESSION['listarticle' . $isfilter])){
        $filter = $_SESSION['listarticle' . $isfilter];
    }
    $articleList = $this->_contentModel->getArticleList($filter, $page, $pageRows);
    $articlecount = $this->_contentModel->getArticleCount($filter);
    $articletypes = $this->_contentModel->getArticleTypeList();
    $selectHtml = $this->_contentModel->getSelectHtml($filter, str_replace('/', '@@@', 'admin/content/getariclerelatedlist'));
    $this->view->render('admin/content/articlerelatedlist.phtml', array(
      'articleList' => $articleList,
      'articletypes' => $articletypes,
      'selectHtml' => $selectHtml,
      'pagination' => pagination('admin/content/getariclerelatedlist/%d/' . $isfilter, $articlecount, $pageRows, $page),
    ));
  }

  public function getAricleRelatedSelectAction()
  {
    if (!access('edit article')) {
      goto403('Do not have access');
    }
    $taxonomyInstance = Taxonomy_Model::getInstance();
    unset($_SESSION['selectarticle']);
  	$articletypes = $this->_contentModel->getArticleTypeList();
  	$directoryList = $taxonomyInstance->getTermsListByType(Taxonomy_Model::TYPE_DIRECTORY);
	  $this->view->render('admin/content/articleselect.phtml', array(
	    'articletypes' => isset($articletypes) ? $articletypes : null,
	    'directoryList' => $directoryList,
	    'posturl' => url('admin/content/getariclerelatedlist'),
	    'relateStyle' => '<style>body{background-color:#FFFFFF;}</style>',
	  ));
  }

  public function getPageListAction($page = 1)
  {
    if (!access('list page')) {
      goto403('Do not have access');
    }
  	$pageRows = 15;
  	$pageList = $this->_contentModel->getPageList($page, $pageRows);
  	$pagecount = $this->_contentModel->getPageCount();
  	$this->view->render('admin/content/getpagelist.phtml', array(
      'pageList' => $pageList,
      'pagination' => pagination('admin/content/getpagelist/%d', $pagecount, $pageRows, $page),
    ));
  }

  public function deletePageAction($pid)
  {
    if (!access('delete page')) {
      goto403('Do not have access');
    }
  	$pageInfo = $this->_contentModel->getPageInfo($pid);
  	if ($pageInfo) {
  		if ($pageInfo->visible == 2) {
  			setMessage('默认页面不能删除', 'error');
  		} else {
	  		if ($this->_contentModel->deletePage($pid)) {
	  			$siteModel = Site_Model::getInstance();
	  			$siteModel->deletePathAlias('page/'.$pid);
	  			setMessage('删除页面信息成功', 'notice');
	  		} else {
	  			setMessage('删除页面信息错误', 'error');
	  		}
  		}
  	} else {
  		setMessage('This page can not found', 'error');
  	}
  	gotourl('admin/content/getpagelist');
  }

  public function editPageAction($pid = 0)
  {
    if (!access('edit page')) {
      goto403('Do not have access');
    }
  	if ($this->isPost()) {
  		$post = $_POST;
  	  $post['pid'] = isset($post['pid']) ? $post['pid'] : null;
      if ($post['pid'] && !$this->_contentModel->getPageInfo($post['pid'])) {
        setMessage('This page can not fount', 'error');
        gotourl('admin/content/getPageList');
      }
  		$post['visible'] = isset($post['visible']) && $post['visible']=='on' ? 1 : 0;
      $pageInstance = PageVariable_Model::getInstance();
      if (isset($_POST['pvTheme']) && $_POST['pvTheme']) {
        $post['pvid'] = $_POST['pvTheme'];
      } else {
        if ($post['pvid']) {
          $post['pvid'] = $pageInstance->updatePageVariables($post['pvid'], $post);
        } else {
          $pvid = $pageInstance->insertPageVariables($post);
          $post['pvid'] = $pvid;
        }
      }
      if (!$post['path_alias']) {
      	$commonInstance = Common_Model::getInstance();
        $post['path_alias'] = $commonInstance->callFunction('translate', urldecode($post['page_title']));
      }
      $post['path_alias'] = $this->_contentModel->getPagePathAlias($post['path_alias'], $post['pid']);
      $siteModel = Site_Model::getInstance();
      if ($post['pid']) {
      	$result = $this->_contentModel->updatePage($post['pid'], $post);
  		  if ($result) {
  		  	$siteModel->savePathAlias('page/'.$post['pid'], $post['path_alias'].'.html');
  				setMessage('修改页面信息成功', 'notice');
  			} elseif($result == '0') {
          setMessage('没有任何更改', 'notice');
        } else {
  				setMessage('修改页面信息错误', 'error');
  			}
  		} else {
  			$post['pid'] = $this->_contentModel->insertPage($post);
        if ($post['pid']) {
        	$siteModel->savePathAlias('page/'.$post['pid'], $post['path_alias'].'.html');
        	setMessage('新增页面信息成功', 'notice');
        } else {
          setMessage('新增页面信息错误', 'error');
        }
  		}
  		gotourl('admin/content/getpagelist');
  	} else {
  	  $pageInstance = PageVariable_Model::getInstance();
  		if ($pid) {
  			$pageInfo = $this->_contentModel->getPageInfo($pid);
  			if (!$pageInfo) {
  			  setMessage('This page can not fount' ,'error');
          gotourl('admin/content/getpagelist');
     	  }
  			if (isset($pageInfo->pvid)) {
          $pageInfo->pv = $pageInstance->selectPageVariables($pageInfo->pvid, 'page', $pageInfo);
  			}
  		}
  		$pvThemes = $pageInstance->getPageVariablesThemeList();
      $this->view->assign('pvThemes', $pvThemes);
  		$this->view->addJs(url('scripts/xheditor/xheditor-zh-cn.min.js'));
  		$this->view->render('admin/content/getpageinfo.phtml', array(
        'pageInfo' => isset($pageInfo) ? $pageInfo : null,
  		  'pv' => isset($pageInfo->pv) ? $pageInfo->pv : null,
      ));
  	}
  }

  public function getTypeAliasAction($str, $type = '')
  {
  	$alias = array('type' => '', 'title' => '');
  	if (!empty($type)) {
      $alias['type'] = $this->getAliasRealize($type);
    }
    $alias['title'] = $this->getAliasRealize($str);
    echo json_encode($alias);exit;
  }
  
  public function getPageAliasAction($str)
  {
  	echo $this->getAliasRealize($str);
  	exit;
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
}