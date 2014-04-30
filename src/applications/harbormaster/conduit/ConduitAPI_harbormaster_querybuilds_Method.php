<?php

final class ConduitAPI_harbormaster_querybuilds_Method
  extends ConduitAPI_harbormaster_Method {

  public function getMethodDescription() {
    return pht('Query Harbormaster builds.');
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'buildStatuses' => 'optional list<string>',
      'buildablePHIDs' => 'optional list<phid>',
      'buildPlanPHIDs' => 'optional list<phid>',
    ) + self::getPagerParamTypes();
  }

  public function defineReturnType() {
    return 'wild';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new HarbormasterBuildQuery())
      ->setViewer($viewer);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids !== null) {
      $query->withPHIDs($phids);
    }

    $statuses = $request->getValue('buildStatuses');
    if ($statuses !== null) {
      $query->withBuildStatuses($statuses);
    }

    $buildable_phids = $request->getValue('buildablePHIDs');
    if ($buildable_phids !== null) {
      $query->withBuildablePHIDs($buildable_phids);
    }

    $build_plan_phids = $request->getValue('buildPlanPHIDs');
    if ($build_plan_phids !== null) {
      $query->withBuildPlanPHIDs($build_plan_phids);
    }

    $pager = $this->newPager($request);

    $builds = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($builds as $build) {

      $id = $build->getID();
      $uri = '/harbormaster/build/'.$id.'/';
      $status = $build->getBuildStatus();

      $data[] = array(
        'id' => $id,
        'phid' => $build->getPHID(),
        'uri' => PhabricatorEnv::getProductionURI($uri),
        'name' => $build->getBuildPlan()->getName(),
        'buildablePHID' => $build->getBuildablePHID(),
        'buildPlanPHID' => $build->getBuildPlanPHID(),
        'buildStatus' => $status,
        'buildStatusName' => HarbormasterBuild::getBuildStatusName($status),
      );
    }

    $results = array(
      'data' => $data,
    );

    $results = $this->addPagerResults($results, $pager);
    return $results;
  }

}
