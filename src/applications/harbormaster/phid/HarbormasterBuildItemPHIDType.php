<?php

final class HarbormasterBuildItemPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMBI';

  public function getTypeName() {
    return pht('Build Item');
  }

  public function newObject() {
    return new HarbormasterBuildItem();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildItemQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build_item = $objects[$phid];
    }
  }

}
