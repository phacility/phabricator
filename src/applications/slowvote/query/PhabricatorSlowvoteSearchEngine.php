<?php

final class PhabricatorSlowvoteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Slowvotes');
  }

  public function getApplicationClassName() {
    return 'PhabricatorSlowvoteApplication';
  }

  public function newQuery() {
    return new PhabricatorSlowvoteQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['voted']) {
      $query->withVotesByViewer(true);
    }

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {

    $status_options = SlowvotePollStatus::getAll();
    $status_options = mpull($status_options, 'getName');

    return array(
      id(new PhabricatorUsersSearchField())
        ->setKey('authorPHIDs')
        ->setAliases(array('authors'))
        ->setLabel(pht('Authors')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('voted')
        ->setLabel(pht('Voted'))

        // TODO: This should probably become a list of "voterPHIDs", so hide
        // the field from Conduit to avoid a backward compatibility break when
        // this changes.

        ->setEnableForConduit(false)
        ->setOptions(array(
          'voted' => pht("Show only polls I've voted in."),
          )),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Statuses'))
        ->setOptions($status_options),
    );
  }

  protected function getURI($path) {
    return '/vote/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open Polls'),
      'all' => pht('All Polls'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
      $names['voted'] = pht('Voted In');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'open':
        return $query->setParameter('statuses', array('open'));
      case 'all':
        return $query;
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
      case 'voted':
        return $query->setParameter('voted', array('voted'));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $polls,
    PhabricatorSavedQuery $query) {
    return mpull($polls, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $polls,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($polls, 'PhabricatorSlowvotePoll');
    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $phids = mpull($polls, 'getAuthorPHID');

    foreach ($polls as $poll) {
      $date_created = phabricator_datetime($poll->getDateCreated(), $viewer);
      if ($poll->getAuthorPHID()) {
        $author = $handles[$poll->getAuthorPHID()]->renderLink();
      } else {
        $author = null;
      }

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($poll)
        ->setObjectName($poll->getMonogram())
        ->setHeader($poll->getQuestion())
        ->setHref($poll->getURI())
        ->addIcon('none', $date_created);

      if ($poll->isClosed()) {
        $item->setStatusIcon('fa-ban grey');
        $item->setDisabled(true);
      } else {
        $item->setStatusIcon('fa-bar-chart');
      }

      $description = $poll->getDescription();
      if (strlen($description)) {
        $item->addAttribute(id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(120)
          ->truncateString($poll->getDescription()));
      }

      if ($author) {
        $item->addByline(pht('Author: %s', $author));
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No polls found.'));

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Poll'))
      ->setHref('/vote/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Poll other users to help facilitate decision making.'))
      ->addAction($create_button);

      return $view;
  }

}
