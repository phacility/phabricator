<?php

final class PhabricatorApplicationPHIDTypeApplication
  extends PhabricatorPHIDType {

  const TYPECONST = 'APPS';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Application');
  }

  public function newObject() {
    return null;
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorApplicationQuery())
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
      $application = $objects[$phid];

      $handle->setName($application->getName());
      $handle->setURI($application->getApplicationURI());
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
