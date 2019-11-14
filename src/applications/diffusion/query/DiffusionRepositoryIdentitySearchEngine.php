<?php

final class DiffusionRepositoryIdentitySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Repository Identities');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return new PhabricatorRepositoryIdentityQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Matching Users'))
        ->setKey('effectivePHIDs')
        ->setAliases(
          array(
            'effective',
            'effectivePHID',
          ))
        ->setDescription(pht('Search for identities by effective user.')),
      id(new DiffusionIdentityAssigneeSearchField())
        ->setLabel(pht('Assigned To'))
        ->setKey('assignedPHIDs')
        ->setAliases(
          array(
            'assigned',
            'assignedPHID',
          ))
        ->setDescription(pht('Search for identities by explicit assignee.')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Identity Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for identities by substring.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Has Matching User'))
        ->setKey('hasEffectivePHID')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Identities With Matching Users'),
          pht('Show Identities Without Matching Users')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['hasEffectivePHID'] !== null) {
      $query->withHasEffectivePHID($map['hasEffectivePHID']);
    }

    if ($map['match'] !== null) {
      $query->withIdentityNameLike($map['match']);
    }

    if ($map['assignedPHIDs']) {
      $query->withAssignedPHIDs($map['assignedPHIDs']);
    }

    if ($map['effectivePHIDs']) {
      $query->withEffectivePHIDs($map['effectivePHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/diffusion/identity/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Identities'),
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

  protected function renderResultList(
    array $identities,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($identities, 'PhabricatorRepositoryIdentity');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);

    $phids = array();
    foreach ($identities as $identity) {
      $phids[] = $identity->getCurrentEffectiveUserPHID();
      $phids[] = $identity->getManuallySetUserPHID();
    }

    $handles = $viewer->loadHandles($phids);

    $unassigned = DiffusionIdentityUnassignedDatasource::FUNCTION_TOKEN;

    foreach ($identities as $identity) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($identity->getObjectName())
        ->setHeader($identity->getIdentityShortName())
        ->setHref($identity->getURI())
        ->setObject($identity);

      $status_icon = 'fa-circle-o grey';

      $effective_phid = $identity->getCurrentEffectiveUserPHID();
      if ($effective_phid) {
        $item->addIcon(
          'fa-id-badge',
          pht('Matches User: %s', $handles[$effective_phid]->getName()));

        $status_icon = 'fa-id-badge';
      }

      $assigned_phid = $identity->getManuallySetUserPHID();
      if ($assigned_phid) {
        if ($assigned_phid === $unassigned) {
          $item->addIcon(
            'fa-ban',
            pht('Explicitly Unassigned'));
          $status_icon = 'fa-ban';
        } else {
          $item->addIcon(
            'fa-user',
            pht('Assigned To: %s', $handles[$assigned_phid]->getName()));
          $status_icon = 'fa-user';
        }
      }

      $item->setStatusIcon($status_icon);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No Identities found.'));

    return $result;
  }

}
