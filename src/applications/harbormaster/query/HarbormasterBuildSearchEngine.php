<?php

final class HarbormasterBuildSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Builds');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function newQuery() {
    return new HarbormasterBuildQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Build Plans'))
        ->setKey('plans')
        ->setAliases(array('plan'))
        ->setDescription(
          pht('Search for builds running a given build plan.'))
        ->setDatasource(new HarbormasterBuildPlanDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Statuses'))
        ->setKey('statuses')
        ->setAliases(array('status'))
        ->setDescription(
          pht('Search for builds with given statuses.'))
        ->setDatasource(new HarbormasterBuildStatusDatasource()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['plans']) {
      $query->withBuildPlanPHIDs($map['plans']);
    }

    if ($map['statuses']) {
      $query->withBuildStatuses($map['statuses']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/harbormaster/build/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Builds'),
    );
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
    array $builds,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($builds, 'HarbormasterBuild');

    $viewer = $this->requireViewer();

    $buildables = mpull($builds, 'getBuildable');
    $object_phids = mpull($buildables, 'getBuildablePHID');
    $initiator_phids = mpull($builds, 'getInitiatorPHID');
    $phids = array_mergev(array($initiator_phids, $object_phids));
    $phids = array_unique(array_filter($phids));

    $handles = $viewer->loadHandles($phids);

    $list = new PHUIObjectItemListView();
    foreach ($builds as $build) {
      $id = $build->getID();
      $initiator = $handles[$build->getInitiatorPHID()];
      $buildable_object = $handles[$build->getBuildable()->getBuildablePHID()];

      $item = id(new PHUIObjectItemView())
        ->setViewer($viewer)
        ->setObject($build)
        ->setObjectName(pht('Build %d', $build->getID()))
        ->setHeader($build->getName())
        ->setHref($build->getURI())
        ->setEpoch($build->getDateCreated())
        ->addAttribute($buildable_object->getName());

      if ($initiator) {
        $item->addHandleIcon($initiator, $initiator->getName());
      }

      $status = $build->getBuildStatus();

      $status_icon = HarbormasterBuild::getBuildStatusIcon($status);
      $status_color = HarbormasterBuild::getBuildStatusColor($status);
      $status_label = HarbormasterBuild::getBuildStatusName($status);

      $item->setStatusIcon("{$status_icon} {$status_color}", $status_label);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No builds found.'));

    return $result;
  }

}
