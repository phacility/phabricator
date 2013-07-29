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

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {
    return id(new PhabricatorRepositoryArcanistProjectQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
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
