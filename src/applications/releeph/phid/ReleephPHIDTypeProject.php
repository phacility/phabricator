<?php

final class ReleephPHIDTypeProject extends PhabricatorPHIDType {

  const TYPECONST = 'REPR';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Releeph Project');
  }

  public function newObject() {
    return new ReleephProject();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ReleephProjectQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $project = $objects[$phid];

      $handle->setName($project->getName());
      $handle->setURI($project->getURI());
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
