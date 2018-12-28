<?php

final class PholioMockSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Pholio Mocks');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPholioApplication';
  }

  public function newQuery() {
    return id(new PholioMockQuery())
      ->needCoverFiles(true)
      ->needImages(true)
      ->needTokenCounts(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setKey('authorPHIDs')
        ->setAliases(array('authors'))
        ->setLabel(pht('Authors')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setOptions(
          id(new PholioMock())
            ->getStatuses()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/pholio/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open Mocks'),
      'all' => pht('All Mocks'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'open':
        return $query->setParameter(
          'statuses',
          array('open'));
      case 'all':
        return $query;
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $mocks,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($mocks, 'PholioMock');

    $viewer = $this->requireViewer();
    $handles = $viewer->loadHandles(mpull($mocks, 'getAuthorPHID'));

    $xform = PhabricatorFileTransform::getTransformByKey(
      PhabricatorFileThumbnailTransform::TRANSFORM_PINBOARD);

    $board = new PHUIPinboardView();
    foreach ($mocks as $mock) {

      $image = $mock->getCoverFile();
      $image_uri = $image->getURIForTransform($xform);
      list($x, $y) = $xform->getTransformedDimensions($image);

      $header = 'M'.$mock->getID().' '.$mock->getName();
      $item = id(new PHUIPinboardItemView())
        ->setUser($viewer)
        ->setHeader($header)
        ->setObject($mock)
        ->setURI('/M'.$mock->getID())
        ->setImageURI($image_uri)
        ->setImageSize($x, $y)
        ->setDisabled($mock->isClosed())
        ->addIconCount('fa-picture-o', count($mock->getActiveImages()))
        ->addIconCount('fa-trophy', $mock->getTokenCount());

      if ($mock->getAuthorPHID()) {
        $author_handle = $handles[$mock->getAuthorPHID()];
        $datetime = phabricator_date($mock->getDateCreated(), $viewer);
        $item->appendChild(
          pht('By %s on %s', $author_handle->renderLink(), $datetime));
      }

      $board->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($board);

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Mock'))
      ->setHref('/pholio/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Upload sets of images for review with revision history and '.
          'inline comments.'))
      ->addAction($create_button);

      return $view;
  }

}
