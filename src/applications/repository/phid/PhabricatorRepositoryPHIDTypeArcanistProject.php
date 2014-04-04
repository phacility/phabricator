<?php

/**
 * @group repository
 */
final class PhabricatorRepositoryPHIDTypeArcanistProject
  extends PhabricatorPHIDType {

  const TYPECONST = 'APRJ';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

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
