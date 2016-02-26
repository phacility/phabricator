<?php

final class HarbormasterBuildableSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Buildables');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function newQuery() {
    return new HarbormasterBuildableQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchStringListField())
        ->setKey('objectPHIDs')
        ->setAliases(array('objects'))
        ->setLabel(pht('Objects'))
        ->setPlaceholder(pht('rXabcdef, PHID-DIFF-1234, ...'))
        ->setDescription(pht('Search for builds of particular objects.')),
      id(new PhabricatorSearchStringListField())
        ->setKey('containerPHIDs')
        ->setAliases(array('containers'))
        ->setLabel(pht('Containers'))
        ->setPlaceholder(pht('rXYZ, R123, D456, ...'))
        ->setDescription(
          pht('Search for builds by containing revision or repository.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Statuses'))
        ->setOptions(HarbormasterBuildable::getBuildStatusMap())
        ->setDescription(pht('Search for builds by buildable status.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Manual'))
        ->setKey('manual')
        ->setDescription(
          pht('Search for only manual or automatic buildables.'))
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Manual Builds'),
          pht('Show Only Automated Builds')),
    );
  }

  private function resolvePHIDs(array $names) {
    $viewer = $this->requireViewer();

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($names)
      ->execute();

    // TODO: Instead of using string lists, we should ideally be using some
    // kind of smart field with resolver logic that can help users type the
    // right stuff. For now, just return a bogus value here so nothing matches
    // but the form doesn't explode.
    if (!$objects) {
      return array('-');
    }

    return mpull($objects, 'getPHID');
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['objectPHIDs']) {
      $phids = $this->resolvePHIDs($map['objectPHIDs']);
      if ($phids) {
        $query->withBuildablePHIDs($phids);
      }
    }

    if ($map['containerPHIDs']) {
      $phids = $this->resolvePHIDs($map['containerPHIDs']);
      if ($phids) {
        $query->withContainerPHIDs($phids);
      }
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    if ($map['manual'] !== null) {
      $query->withManualBuildables($map['manual']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/harbormaster/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Buildables'),
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
    array $buildables,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($buildables, 'HarbormasterBuildable');

    $viewer = $this->requireViewer();

    $phids = array();
    foreach ($buildables as $buildable) {
      $phids[] = $buildable->getBuildableObject()
        ->getHarbormasterBuildableDisplayPHID();

      $phids[] = $buildable->getContainerPHID();
      $phids[] = $buildable->getBuildablePHID();
    }
    $handles = $viewer->loadHandles($phids);


    $list = new PHUIObjectItemListView();
    foreach ($buildables as $buildable) {
      $id = $buildable->getID();

      $display_phid = $buildable->getBuildableObject()
        ->getHarbormasterBuildableDisplayPHID();

      $container_phid = $buildable->getContainerPHID();
      $buildable_phid = $buildable->getBuildablePHID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Buildable %d', $buildable->getID()));

      if ($display_phid) {
        $handle = $handles[$display_phid];
        $item->setHeader($handle->getFullName());
      }

      if ($container_phid && ($container_phid != $display_phid)) {
        $handle = $handles[$container_phid];
        $item->addAttribute($handle->getName());
      }

      if ($buildable_phid && ($buildable_phid != $display_phid)) {
        $handle = $handles[$buildable_phid];
        $item->addAttribute($handle->getFullName());
      }

      $item->setHref($buildable->getURI());

      if ($buildable->getIsManualBuildable()) {
        $item->addIcon('fa-wrench grey', pht('Manual'));
      }

      $status = $buildable->getBuildableStatus();

      $status_icon = HarbormasterBuildable::getBuildableStatusIcon($status);
      $status_color = HarbormasterBuildable::getBuildableStatusColor($status);
      $status_label = HarbormasterBuildable::getBuildableStatusName($status);

      $item->setStatusIcon("{$status_icon} {$status_color}", $status_label);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No buildables found.'));

    return $result;
  }

}
