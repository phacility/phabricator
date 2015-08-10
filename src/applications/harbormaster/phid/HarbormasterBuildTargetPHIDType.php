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
      $target = $objects[$phid];
      $target_id = $target->getID();

      // Build target don't currently have their own page, so just point
      // the user at the build until we have one.
      $build = $target->getBuild();
      $build_id = $build->getID();
      $uri = "/harbormaster/build/{$build_id}/";

      $handle->setName(pht('Build Target %d', $target_id));
      $handle->setURI($uri);
    }
  }

}
