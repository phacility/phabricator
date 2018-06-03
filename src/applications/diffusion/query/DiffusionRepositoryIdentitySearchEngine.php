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
      id(new DiffusionIdentityAssigneeSearchField())
        ->setLabel(pht('Assigned To'))
        ->setKey('assignee')
        ->setDescription(pht('Search for identities by assignee.')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Identity Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for identities by substring.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Is Assigned'))
        ->setKey('hasEffectivePHID')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Assigned Identities'),
          pht('Show Only Unassigned Identities')),
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

    if ($map['assignee']) {
      $query->withAssigneePHIDs($map['assignee']);
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

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($identities as $identity) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Identity %d', $identity->getID()))
        ->setHeader($identity->getIdentityShortName())
        ->setHref($identity->getURI())
        ->setObject($identity);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No Identities found.'));

    return $result;
  }

}
