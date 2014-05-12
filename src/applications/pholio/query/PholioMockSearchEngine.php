<?php

final class PholioMockSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getApplicationClassName() {
    return 'PhabricatorApplicationPholio';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PholioMockQuery())
      ->needCoverFiles(true)
      ->needImages(true)
      ->needTokenCounts(true)
      ->withAuthorPHIDs($saved->getParameter('authorPHIDs', array()));

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

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_handles));
  }

  protected function getURI($path) {
    return '/pholio/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
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
      $item = id(new PHUIPinboardItemView())
        ->setHeader('M'.$mock->getID().' '.$mock->getName())
        ->setURI('/M'.$mock->getID())
        ->setImageURI($mock->getCoverFile()->getThumb280x210URI())
        ->setImageSize(280, 210)
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
