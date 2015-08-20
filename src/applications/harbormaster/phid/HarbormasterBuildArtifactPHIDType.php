<?php

final class HarbormasterBuildArtifactPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMBA';

  public function getTypeName() {
    return pht('Build Artifact');
  }

  public function newObject() {
    return new HarbormasterBuildArtifact();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildArtifactQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $artifact = $objects[$phid];
      $artifact_id = $artifact->getID();
      $handle->setName(pht('Build Artifact %d', $artifact_id));
    }
  }

}
