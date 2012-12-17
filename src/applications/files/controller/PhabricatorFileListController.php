<?php

final class PhabricatorFileListController extends PhabricatorFileController {
  private $filter;

  private $useBasicUploader = false;

  private $listAuthor;
  private $listRows;
  private $listRowClasses;

  private function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }
  private function getFilter() {
    return $this->filter;
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

  public function willProcessRequest(array $data) {
    $this->setFilter(idx($data, 'filter', 'upload'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $query = id(new PhabricatorFileQuery())
      ->setViewer($user);

    $show_pager = true;
    $show_upload = false;

    switch ($this->getFilter()) {
      case 'upload':
      default:
        $this->setUseBasicUploader($request->getExists('basic_uploader'));

        $query->withAuthorPHIDs(array($user->getPHID()));
        $pager->setPageSize(10);

        $header = pht('Recently Uploaded Files');
        $show_pager = false;
        $show_upload = true;
        break;
      case 'my':
        $query->withAuthorPHIDs(array($user->getPHID()));
        $header = pht('Files You Uploaded');
        break;
      case 'all':
        $header = pht('All Files');
        break;
    }

    $files = $query->executeWithCursorPager($pager);
    $this->loadHandles(mpull($files, 'getAuthorPHID'));

    $highlighted = $request->getStrList('h');
    $file_list = $this->buildFileList($files, $highlighted);

    $side_nav = $this->buildSideNavView();
    $side_nav->selectFilter($this->getFilter());
    if ($show_upload) {
      $side_nav->appendChild($this->renderUploadPanel());
    }

    $header_view = id(new PhabricatorHeaderView())
      ->setHeader($header);

    $side_nav->appendChild(
      array(
        $header_view,
        $file_list,
        $show_pager ? $pager : null,
      ));

    return $this->buildApplicationPage(
      $side_nav,
      array(
        'title' => 'Files',
      ));
  }

  private function buildFileList(array $files, array $highlighted_ids) {
    assert_instances_of($files, 'PhabricatorFile');

    $request = $this->getRequest();
    $user = $request->getUser();

    $highlighted_ids = array_fill_keys($highlighted_ids, true);

    $list_view = id(new PhabricatorObjectItemListView())
      ->setViewer($user);

    foreach ($files as $file) {
      $id = $file->getID();
      $phid = $file->getPHID();
      $name = $file->getName();

      $file_name = "F{$id} {$name}";
      $file_uri = $this->getApplicationURI("/info/{$phid}/");

      $date_created = phabricator_date($file->getDateCreated(), $user);

      $author_phid = $file->getAuthorPHID();
      if ($author_phid) {
        $author_link = $this->getHandle($author_phid)->renderLink();
        $uploaded = pht('Uploaded by %s on %s', $author_link, $date_created);
      } else {
        $uploaded = pht('Uploaded on %s', $date_created);
      }

      $item = id(new PhabricatorObjectItemView())
        ->setObject($file)
        ->setHeader($file_name)
        ->setHref($file_uri)
        ->addAttribute($uploaded)
        ->addIcon('none', phabricator_format_bytes($file->getByteSize()));

      if (isset($highlighted_ids[$id])) {
        $item->setEffect('highlighted');
      }

      $list_view->addItem($item);
    }

    return $list_view;
  }

  private function buildSideNavView() {
    $view = new AphrontSideNavFilterView();
    $view->setBaseURI(new PhutilURI($this->getApplicationURI('/filter/')));

    $view->addLabel('Files');
    $view->addFilter('upload', 'Upload File');
    $view->addFilter('my', 'My Files');
    $view->addFilter('all', 'All Files');

    return $view;
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
