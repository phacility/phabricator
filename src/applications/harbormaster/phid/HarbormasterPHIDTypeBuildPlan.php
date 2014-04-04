<?php

final class HarbormasterPHIDTypeBuildPlan extends PhabricatorPHIDType {

  const TYPECONST = 'HMCP';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Build Plan');
  }

  public function newObject() {
    return new HarbormasterBuildPlan();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildPlanQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build_plan = $objects[$phid];
      $handles[$phid]->setName($build_plan->getName());
      $handles[$phid]->setURI('/harbormaster/plan/'.$build_plan->getID());
    }
  }

}
