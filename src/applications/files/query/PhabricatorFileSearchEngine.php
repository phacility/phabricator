<?php

final class PhabricatorFileSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Files');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFilesApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    $query = new PhabricatorFileQuery();
    $query->withIsDeleted(false);
    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors'))
        ->setLabel(pht('Authors')),
      id(new PhabricatorSearchThreeStateField())
        ->setKey('explicit')
        ->setLabel(pht('Upload Source'))
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Manually Uploaded Files'),
          pht('Hide Manually Uploaded Files')),
      id(new PhabricatorSearchDateField())
        ->setKey('createdStart')
        ->setLabel(pht('Created After')),
      id(new PhabricatorSearchDateField())
        ->setKey('createdEnd')
        ->setLabel(pht('Created Before')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name')
        ->setDescription(pht('Search for files by name substring.')),
    );
  }

  protected function getDefaultFieldOrder() {
    return array(
      '...',
      'createdStart',
      'createdEnd',
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['explicit'] !== null) {
      $query->showOnlyExplicitUploads($map['explicit']);
    }

    if ($map['createdStart']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    if ($map['createdEnd']) {
      $query->withDateCreatedBefore($map['createdEnd']);
    }

    if ($map['name'] !== null) {
      $query->withNameNgrams($map['name']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/file/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    $names += array(
      'all' => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'authored':
        $author_phid = array($this->requireViewer()->getPHID());
        return $query
          ->setParameter('authorPHIDs', $author_phid)
          ->setParameter('explicit', true);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $files,
    PhabricatorSavedQuery $query) {
    return mpull($files, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $files,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($files, 'PhabricatorFile');

    $request = $this->getRequest();
    if ($request) {
      $highlighted_ids = $request->getStrList('h');
    } else {
      $highlighted_ids = array();
    }

    $viewer = $this->requireViewer();

    $highlighted_ids = array_fill_keys($highlighted_ids, true);

    $list_view = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($files as $file) {
      $id = $file->getID();
      $phid = $file->getPHID();
      $name = $file->getName();
      $file_uri = $this->getApplicationURI("/info/{$phid}/");

      $date_created = phabricator_date($file->getDateCreated(), $viewer);
      $author_phid = $file->getAuthorPHID();
      if ($author_phid) {
        $author_link = $handles[$author_phid]->renderLink();
        $uploaded = pht('Uploaded by %s on %s', $author_link, $date_created);
      } else {
        $uploaded = pht('Uploaded on %s', $date_created);
      }

      $item = id(new PHUIObjectItemView())
        ->setObject($file)
        ->setObjectName("F{$id}")
        ->setHeader($name)
        ->setHref($file_uri)
        ->addAttribute($uploaded)
        ->addIcon('none', phutil_format_bytes($file->getByteSize()));

      $ttl = $file->getTTL();
      if ($ttl !== null) {
        $item->addIcon('blame', pht('Temporary'));
      }

      if ($file->getIsPartial()) {
        $item->addIcon('fa-exclamation-triangle orange', pht('Partial'));
      }

      if (isset($highlighted_ids[$id])) {
        $item->setEffect('highlighted');
      }

      $list_view->addItem($item);
    }

    $list_view->appendChild(id(new PhabricatorGlobalUploadTargetView())
      ->setUser($viewer));


    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($list_view);

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Upload a File'))
      ->setHref('/file/upload/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Just a place for files.'))
      ->addAction($create_button);

      return $view;
  }

}
