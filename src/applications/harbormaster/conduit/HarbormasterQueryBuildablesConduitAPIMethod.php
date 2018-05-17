<?php

final class HarbormasterQueryBuildablesConduitAPIMethod
  extends HarbormasterConduitAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.querybuildables';
  }

  public function getMethodDescription() {
    return pht('Query Harbormaster buildables.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'buildablePHIDs' => 'optional list<phid>',
      'containerPHIDs' => 'optional list<phid>',
      'manualBuildables' => 'optional bool',
    ) + self::getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'wild';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids !== null) {
      $query->withPHIDs($phids);
    }

    $buildable_phids = $request->getValue('buildablePHIDs');
    if ($buildable_phids !== null) {
      $query->withBuildablePHIDs($buildable_phids);
    }

    $container_phids = $request->getValue('containerPHIDs');
    if ($container_phids !== null) {
      $query->withContainerPHIDs($container_phids);
    }

    $manual = $request->getValue('manualBuildables');
    if ($manual !== null) {
      $query->withManualBuildables($manual);
    }

    $pager = $this->newPager($request);

    $buildables = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($buildables as $buildable) {
      $monogram = $buildable->getMonogram();

      $status = $buildable->getBuildableStatus();
      $status_name = $buildable->getStatusDisplayName();

      $data[] = array(
        'id' => $buildable->getID(),
        'phid' => $buildable->getPHID(),
        'monogram' => $monogram,
        'uri' => PhabricatorEnv::getProductionURI('/'.$monogram),
        'buildableStatus' => $status,
        'buildableStatusName' => $status_name,
        'buildablePHID' => $buildable->getBuildablePHID(),
        'containerPHID' => $buildable->getContainerPHID(),
        'isManualBuildable' => (bool)$buildable->getIsManualBuildable(),
      );
    }

    $results = array(
      'data' => $data,
    );

    $results = $this->addPagerResults($results, $pager);
    return $results;
  }

}
