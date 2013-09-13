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

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ReleephProjectQuery())
      ->setViewer($query->getViewer())
      ->setParentQuery($query)
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
      $handle->setURI($project->getURI());
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
