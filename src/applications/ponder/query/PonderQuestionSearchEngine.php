<?php

final class PonderQuestionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Ponder Questions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPonderApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter(
      'answererPHIDs',
      $this->readUsersFromRequest($request, 'answerers'));

    $saved->setParameter('status', $request->getStr('status'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PonderQuestionQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    $answerer_phids = $saved->getParameter('answererPHIDs');
    if ($answerer_phids) {
      $query->withAnswererPHIDs($answerer_phids);
    }

    $status = $saved->getParameter('status');
    if ($status != null) {
      switch ($status) {
        case 0:
          $query->withStatus(PonderQuestionQuery::STATUS_OPEN);
          break;
        case 1:
          $query->withStatus(PonderQuestionQuery::STATUS_CLOSED);
          break;
      }
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $author_phids = $saved_query->getParameter('authorPHIDs', array());
    $answerer_phids = $saved_query->getParameter('answererPHIDs', array());
    $status = $saved_query->getParameter(
      'status', PonderQuestionStatus::STATUS_OPEN);

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('answerers')
          ->setLabel(pht('Answered By'))
          ->setValue($answerer_phids))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setValue($status)
          ->setOptions(PonderQuestionStatus::getQuestionStatusMap()));
  }

  protected function getURI($path) {
    return '/ponder/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open Questions'),
      'all' => pht('All Questions'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
      $names['answered'] = pht('Answered');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'open':
        return $query->setParameter('status', PonderQuestionQuery::STATUS_OPEN);
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
      case 'answered':
        return $query->setParameter(
          'answererPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $questions,
    PhabricatorSavedQuery $query) {
    return mpull($questions, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $questions,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($questions, 'PonderQuestion');

    $viewer = $this->requireViewer();

    $view = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($questions as $question) {
      $item = new PHUIObjectItemView();
      $item->setObjectName('Q'.$question->getID());
      $item->setHeader($question->getTitle());
      $item->setHref('/Q'.$question->getID());
      $item->setObject($question);
      $item->setBarColor(
        PonderQuestionStatus::getQuestionStatusTagColor(
          $question->getStatus()));

      $created_date = phabricator_date($question->getDateCreated(), $viewer);
      $item->addIcon('none', $created_date);
      $item->addByline(
        pht(
          'Asked by %s',
          $handles[$question->getAuthorPHID()]->renderLink()));

      $item->addAttribute(
        pht('%d Answer(s)', $question->getAnswerCount()));

      $view->addItem($item);
    }

    return $view;
  }

}
