<?php

final class PonderQuestionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Ponder Questions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPonderApplication';
  }

  public function newQuery() {
    return new PonderQuestionQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['answerers']) {
      $query->withAnswererPHIDs($map['answerers']);
    }

    $status = $map['status'];
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

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setKey('authorPHIDs')
        ->setAliases(array('authors'))
        ->setLabel(pht('Authors')),
      id(new PhabricatorUsersSearchField())
        ->setKey('answerers')
        ->setAliases(array('answerers'))
        ->setLabel(pht('Answered By')),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions(PonderQuestionStatus::getQuestionStatusMap()),
    );
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
      $color = PonderQuestionStatus::getQuestionStatusTagColor(
          $question->getStatus());
      $icon = PonderQuestionStatus::getQuestionStatusIcon(
          $question->getStatus());
      $full_status = PonderQuestionStatus::getQuestionStatusFullName(
          $question->getStatus());
      $item = new PHUIObjectItemView();
      $item->setObjectName('Q'.$question->getID());
      $item->setHeader($question->getTitle());
      $item->setHref('/Q'.$question->getID());
      $item->setObject($question);
      $item->setStatusIcon($icon.' '.$color, $full_status);

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

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($view);
    $result->setNoDataString(pht('No questions found.'));

    return $result;
  }

}
