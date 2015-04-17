<?php

final class PhabricatorApplicationApplicationPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'APPS';

  public function getTypeName() {
    return pht('Application');
  }

  public function getTypeIcon() {
    return 'fa-globe';
  }

  public function newObject() {
    return null;
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorApplicationQuery())
      ->withPHIDs($phids);
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

}
