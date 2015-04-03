<?php

final class PhabricatorFileSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Files');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFilesApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('explicit', $request->getBool('explicit'));
    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorFileQuery());

    $author_phids = $saved->getParameter('authorPHIDs', array());
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    if ($saved->getParameter('explicit')) {
      $query->showOnlyExplicitUploads(true);
    }

    $start = $this->parseDateTime($saved->getParameter('createdStart'));
    $end = $this->parseDateTime($saved->getParameter('createdEnd'));

    if ($start) {
      $query->withDateCreatedAfter($start);
    }

    if ($end) {
      $query->withDateCreatedBefore($end);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $author_phids = $saved_query->getParameter('authorPHIDs', array());
    $explicit = $saved_query->getParameter('explicit');

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_phids))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'explicit',
            1,
            pht('Show only manually uploaded files.'),
            $explicit));

    $this->buildDateRange(
      $form,
      $saved_query,
      'createdStart',
      pht('Created After'),
      'createdEnd',
      pht('Created Before'));
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

    return $list_view;
  }

}
