<?php

final class PhabricatorFileListController extends PhabricatorFileController {
  private $filter;

  private $showUploader;
  private $useBasicUploader = false;

  private $listAuthor;
  private $listRows;
  private $listRowClasses;
  private $listHeader;
  private $showListPager = true;
  private $listPager;
  private $pagerOffset;
  private $pagerPageSize;

  private function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }
  private function getFilter() {
    return $this->filter;
  }

  private function showUploader() {
    return $this->getShowUploader();
  }
  private function getShowUploader() {
    return $this->showUploader;
  }
  private function setShowUploader($show_uploader) {
    $this->showUploader = $show_uploader;
    return $this;
  }

  private function useBasicUploader() {
    return $this->getUseBasicUploader();
  }
  private function getUseBasicUploader() {
    return $this->useBasicUploader;
  }
  private function setUseBasicUploader($use_basic_uploader) {
    $this->useBasicUploader = $use_basic_uploader;
    return $this;
  }

  private function setListAuthor(PhabricatorUser $list_author) {
    $this->listAuthor = $list_author;
    return $this;
  }
  private function getListAuthor() {
    return $this->listAuthor;
  }

  private function getListRows() {
    return $this->listRows;
  }
  private function setListRows($list_rows) {
    $this->listRows = $list_rows;
    return $this;
  }

  private function getListRowClasses() {
    return $this->listRowClasses;
  }
  private function setListRowClasses($list_row_classes) {
    $this->listRowClasses = $list_row_classes;
    return $this;
  }

  private function getListHeader() {
    return $this->listHeader;
  }
  private function setListHeader($list_header) {
    $this->listHeader = $list_header;
    return $this;
  }

  private function showListPager() {
    return $this->getShowListPager();
  }
  private function getShowListPager() {
    return $this->showListPager;
  }
  private function setShowListPager($show_list_pager) {
    $this->showListPager = $show_list_pager;
    return $this;
  }

  private function getListPager() {
    return $this->listPager;
  }
  private function setListPager($list_pager) {
    $this->listPager = $list_pager;
    return $this;
  }

  private function setPagerOffset($pager_offset) {
    $this->pagerOffset = $pager_offset;
    return $this;
  }
  private function getPagerOffset() {
    return $this->pagerOffset;
  }

  private function setPagerPageSize($pager_page_size) {
    $this->pagerPageSize = $pager_page_size;
    return $this;
  }
  private function getPagerPageSize() {
    return $this->pagerPageSize;
  }

  public function willProcessRequest(array $data) {
    $this->setFilter(idx($data, 'filter', 'upload'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    switch ($this->getFilter()) {
      case 'upload':
      default:
        $this->setShowUploader(true);
        $this->setUseBasicUploader($request->getExists('basic_uploader'));
        $see_all = phutil_render_tag(
          'a',
          array(
            'href' => '/file/filter/all',
          ),
          'See all Files');
        $this->setListHeader("Recently Uploaded Files &middot; {$see_all}");
        $this->setShowListPager(false);
        $this->setPagerOffset(0);
        $this->setPagerPageSize(10);
        break;
      case 'my':
        $this->setShowUploader(false);
        $this->setListHeader('Files You Uploaded');
        $this->setListAuthor($user);
        $this->setPagerOffset($request->getInt('page', 0));
        break;
      case 'all':
        $this->setShowUploader(false);
        $this->setListHeader('All Files');
        $this->setPagerOffset($request->getInt('page', 0));
        break;
    }
    $this->loadListData();

    $side_nav = new PhabricatorFileSideNavView();
    $side_nav->setSelectedFilter($this->getFilter());
    if ($this->showUploader()) {
      $side_nav->appendChild($this->renderUploadPanel());
    }
    $side_nav->appendChild($this->renderList());

    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Files',
      ));
  }

  private function loadListData() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontPagerView();
    $pager->setOffset($this->getPagerOffset());
    if ($this->getPagerPageSize()) {
      $pager->setPageSize($this->getPagerPageSize());
    }

    $author = $this->getListAuthor();
    if ($author) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'authorPHID = %s ORDER BY id DESC LIMIT %d, %d',
        $author->getPHID(),
        $pager->getOffset(),
        $pager->getPageSize() + 1);
    } else {
      $files = id(new PhabricatorFile())->loadAllWhere(
        '1 = 1 ORDER BY id DESC LIMIT %d, %d',
        $pager->getOffset(),
        $pager->getPageSize() + 1);
    }

    $files = $pager->sliceResults($files);
    $pager->setURI($request->getRequestURI(), 'page');
    $this->setListPager($pager);

    $phids = mpull($files, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    $highlighted = $request->getStr('h');
    $highlighted = explode('-', $highlighted);
    $highlighted = array_fill_keys($highlighted, true);

    $rows = array();
    $rowc = array();
    foreach ($files as $file) {
      if ($file->isViewableInBrowser()) {
        $view_button = phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => $file->getViewURI(),
          ),
          'View');
      } else {
        $view_button = null;
      }

      if (isset($highlighted[$file->getID()])) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = '';
      }

      $name = $file->getName();
      $rows[] = array(
        phutil_escape_html('F'.$file->getID()),
        $file->getAuthorPHID()
          ? $handles[$file->getAuthorPHID()]->renderLink()
          : null,
        phutil_render_tag(
          'a',
          array(
            // Don't use $file->getBestURI() to improve discoverability of /F.
            'href' => '/F'.$file->getID(),
          ),
          ($name != '' ? phutil_escape_html($name) : '<em>no name</em>')),
        phutil_escape_html(number_format($file->getByteSize()).' bytes'),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => '/file/info/'.$file->getPHID().'/',
          ),
          'Info'),
        $view_button,
        phabricator_date($file->getDateCreated(), $user),
        phabricator_time($file->getDateCreated(), $user),
      );
    }
    $this->setListRows($rows);
    $this->setListRowClasses($rowc);
  }

  private function renderList() {
    $table = new AphrontTableView($this->getListRows());
    $table->setRowClasses($this->getListRowClasses());
    $table->setHeaders(
      array(
        'File ID',
        'Author',
        'Name',
        'Size',
        '',
        '',
        'Created',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        '',
        'wide pri',
        'right',
        'action',
        'action',
        '',
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader($this->getListHeader());
    if ($this->showListPager()) {
      $panel->appendChild($this->getListPager());
    }

    return $panel;
  }

  private function renderUploadPanel() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $limit_text = PhabricatorFileUploadView::renderUploadLimit();

    if ($this->useBasicUploader()) {

      $upload_panel = new PhabricatorFileUploadView();
      $upload_panel->setUser($user);

    } else {

      require_celerity_resource('files-css');
      $upload_id = celerity_generate_unique_node_id();
      $panel_id  = celerity_generate_unique_node_id();

      $upload_panel = new AphrontPanelView();
      $upload_panel->setHeader('Upload Files');
      $upload_panel->setCaption($limit_text);
      $upload_panel->setCreateButton('Basic Uploader',
        $request->getRequestURI()->setQueryParam('basic_uploader', true)
      );

      $upload_panel->setWidth(AphrontPanelView::WIDTH_FULL);
      $upload_panel->setID($panel_id);

      $upload_panel->appendChild(
        phutil_render_tag(
          'div',
          array(
            'id'    => $upload_id,
            'style' => 'display: none;',
            'class' => 'files-drag-and-drop',
          ),
          ''));

      Javelin::initBehavior(
        'files-drag-and-drop',
        array(
          'uri'             => '/file/dropupload/',
          'browseURI'       => '/file/filter/my/',
          'control'         => $upload_id,
          'target'          => $panel_id,
          'activatedClass'  => 'aphront-panel-view-drag-and-drop',
        ));
    }

    return $upload_panel;
  }
}
