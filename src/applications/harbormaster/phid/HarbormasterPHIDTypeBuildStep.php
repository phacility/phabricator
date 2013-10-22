<?php

final class HarbormasterPHIDTypeBuildStep extends PhabricatorPHIDType {

  const TYPECONST = 'HMCS';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Build Step');
  }

  public function newObject() {
    return new HarbormasterBuildStep();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildStepQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build_step = $objects[$phid];
    }
  }

}
