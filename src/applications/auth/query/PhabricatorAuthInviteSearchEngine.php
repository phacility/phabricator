<?php

final class PhabricatorAuthInviteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Auth Email Invites');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAuthApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorAuthInviteQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {}

  protected function getURI($path) {
    return '/people/invite/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
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
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $invites,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($invites as $invite) {
      $phids[$invite->getAuthorPHID()] = true;
      if ($invite->getAcceptedByPHID()) {
        $phids[$invite->getAcceptedByPHID()] = true;
      }
    }

    return array_keys($phids);
  }

  protected function renderResultList(
    array $invites,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($invites, 'PhabricatorAuthInvite');

    $viewer = $this->requireViewer();

    $rows = array();
    foreach ($invites as $invite) {
      $rows[] = array(
        $invite->getEmailAddress(),
        $handles[$invite->getAuthorPHID()]->renderLink(),
        ($invite->getAcceptedByPHID()
          ? $handles[$invite->getAcceptedByPHID()]->renderLink()
          : null),
        phabricator_datetime($invite->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Email Address'),
          pht('Sent By'),
          pht('Accepted By'),
          pht('Invited'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          'wide',
          'right',
        ));

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($table);

    return $result;
  }
}
