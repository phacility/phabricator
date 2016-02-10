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
    return id(new PonderQuestionQuery())
      ->needProjectPHIDs(true);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['answerers']) {
      $query->withAnswererPHIDs($map['answerers']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
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
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Status'))
        ->setKey('statuses')
        ->setOptions(PonderQuestionStatus::getQuestionStatusMap()),
    );
  }

  protected function getURI($path) {
    return '/ponder/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'recent' => pht('Recent Questions'),
      'open' => pht('Open Questions'),
      'resolved' => pht('Resolved Questions'),
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
        return $query->setParameter(
          'statuses', array(PonderQuestionStatus::STATUS_OPEN));
      case 'recent':
        return $query->setParameter(
          'statuses', array(
            PonderQuestionStatus::STATUS_OPEN,
            PonderQuestionStatus::STATUS_CLOSED_RESOLVED,
          ));
      case 'resolved':
        return $query->setParameter(
          'statuses', array(PonderQuestionStatus::STATUS_CLOSED_RESOLVED));
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
      case 'answered':
        return $query->setParameter(
          'answerers',
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

    $proj_phids = array();
    foreach ($questions as $question) {
      foreach ($question->getProjectPHIDs() as $project_phid) {
        $proj_phids[] = $project_phid;
      }
    }

    $proj_handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($proj_phids)
      ->execute();

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

      $project_handles = array_select_keys(
        $proj_handles,
        $question->getProjectPHIDs());

      $created_date = phabricator_date($question->getDateCreated(), $viewer);
      $item->addIcon('none', $created_date);
      $item->addByline(
        pht(
          'Asked by %s',
          $handles[$question->getAuthorPHID()]->renderLink()));

      $item->addAttribute(
        pht(
          '%s Answer(s)',
          new PhutilNumber($question->getAnswerCount())));

      if ($project_handles) {
        $item->addAttribute(
          id(new PHUIHandleTagListView())
            ->setLimit(4)
            ->setSlim(true)
            ->setHandles($project_handles));
      }

      $view->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($view);
    $result->setNoDataString(pht('No questions found.'));

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Ask a Question'))
      ->setHref('/ponder/question/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('A simple questions and answers application for your teams.'))
      ->addAction($create_button);

      return $view;
  }

}
