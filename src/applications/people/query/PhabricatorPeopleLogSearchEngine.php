<?php

final class PhabricatorPeopleLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getPageSize(PhabricatorSavedQuery $saved) {
    return 500;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'userPHIDs',
      $this->readUsersFromRequest($request, 'users'));

    $saved->setParameter(
      'actorPHIDs',
      $this->readUsersFromRequest($request, 'actors'));

    $saved->setParameter(
      'actions',
      $this->readListFromRequest($request, 'actions'));

    $saved->setParameter(
      'ip',
      $request->getStr('ip'));

    $saved->setParameter(
      'sessions',
      $this->readListFromRequest($request, 'sessions'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorPeopleLogQuery());

    $actor_phids = $saved->getParameter('actorPHIDs', array());
    if ($actor_phids) {
      $query->withActorPHIDs($actor_phids);
    }

    $user_phids = $saved->getParameter('userPHIDs', array());
    if ($user_phids) {
      $query->withUserPHIDs($user_phids);
    }

    $actions = $saved->getParameter('actions', array());
    if ($actions) {
      $query->withActions($actions);
    }

    $remote_prefix = $saved->getParameter('ip');
    if (strlen($remote_prefix)) {
      $query->withRemoteAddressprefix($remote_prefix);
    }

    $sessions = $saved->getParameter('sessions', array());
    if ($sessions) {
      $query->withSessionKeys($sessions);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $actor_phids = $saved->getParameter('actorPHIDs', array());
    $user_phids = $saved->getParameter('userPHIDs', array());

    $all_phids = array_merge(
      $actor_phids,
      $user_phids);

    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $actor_handles = array_select_keys($handles, $actor_phids);
    $user_handles = array_select_keys($handles, $user_phids);

    $actions = $saved->getParameter('actions', array());
    $remote_prefix = $saved->getParameter('ip');
    $sessions = $saved->getParameter('sessions', array());

    $actions = array_fuse($actions);
    $action_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Actions'));
    $action_types = PhabricatorUserLog::getActionTypeMap();
    foreach ($action_types as $type => $label) {
      $action_control->addCheckbox(
        'actions[]',
        $type,
        $label,
        isset($actions[$label]));
    }

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('actors')
          ->setLabel(pht('Actors'))
          ->setValue($actor_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('users')
          ->setLabel(pht('Users'))
          ->setValue($user_handles))
      ->appendChild($action_control)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Filter IP'))
          ->setName('ip')
          ->setValue($remote_prefix))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Sessions'))
          ->setName('sessions')
          ->setValue(implode(', ', $sessions)));

  }

  protected function getURI($path) {
    return '/people/logs/'.$path;
  }

  public function getBuiltinQueryNames() {
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

}
