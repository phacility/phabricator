<?php

final class PholioMockSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Pholio Mocks');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPholioApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));
    $saved->setParameter(
      'statuses',
      $request->getStrList('status'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PholioMockQuery())
      ->needCoverFiles(true)
      ->needImages(true)
      ->needTokenCounts(true)
      ->withAuthorPHIDs($saved->getParameter('authorPHIDs', array()))
      ->withStatuses($saved->getParameter('statuses', array()));

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $phids = $saved_query->getParameter('authorPHIDs', array());
    $author_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $statuses = array(
      '' => pht('Any Status'),
      'closed' => pht('Closed'),
      'open' => pht('Open'),
    );

    $status = $saved_query->getParameter('statuses', array());
    $status = head($status);

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_handles))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setOptions($statuses)
          ->setValue($status));
  }

  protected function getURI($path) {
    return '/pholio/'.$path;
  }

  public function getBuiltinQueryNames() {
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

  protected function getRequiredHandlePHIDsForResultList(
    array $mocks,
    PhabricatorSavedQuery $query) {
    return mpull($mocks, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $mocks,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($mocks, 'PholioMock');

    $viewer = $this->requireViewer();

    $board = new PHUIPinboardView();
    foreach ($mocks as $mock) {

      $header = 'M'.$mock->getID().' '.$mock->getName();
      $item = id(new PHUIPinboardItemView())
        ->setHeader($header)
        ->setURI('/M'.$mock->getID())
        ->setImageURI($mock->getCoverFile()->getThumb280x210URI())
        ->setImageSize(280, 210)
        ->setDisabled($mock->isClosed())
        ->addIconCount('fa-picture-o', count($mock->getImages()))
        ->addIconCount('fa-trophy', $mock->getTokenCount());

      if ($mock->getAuthorPHID()) {
        $author_handle = $handles[$mock->getAuthorPHID()];
        $datetime = phabricator_date($mock->getDateCreated(), $viewer);
        $item->appendChild(
          pht('By %s on %s', $author_handle->renderLink(), $datetime));
      }

      $board->addItem($item);
    }

    return $board;
  }

}
