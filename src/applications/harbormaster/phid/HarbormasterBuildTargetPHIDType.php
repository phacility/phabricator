<?php

final class HarbormasterBuildTargetPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMBT';

  public function getTypeName() {
    return pht('Build Target');
  }

  public function newObject() {
    return new HarbormasterBuildTarget();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildTargetQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build_target = $objects[$phid];
    }
  }

}
