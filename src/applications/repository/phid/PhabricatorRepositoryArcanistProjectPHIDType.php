<?php

final class PhabricatorRepositoryArcanistProjectPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'APRJ';

  public function getTypeName() {
    return pht('Arcanist Project');
  }

  public function newObject() {
    return new PhabricatorRepositoryArcanistProject();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryArcanistProjectQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $project = $objects[$phid];
      $handle->setName($project->getName());
    }
  }

}
