<?php

final class PhabricatorCalendarImportPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CIMP';

  public function getTypeName() {
    return pht('Calendar Import');
  }

  public function newObject() {
    return new PhabricatorCalendarImport();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorCalendarImportQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $import = $objects[$phid];

      $id = $import->getID();
      $name = $import->getDisplayName();
      $uri = $import->getURI();

      $handle
        ->setName($name)
        ->setFullName(pht('Calendar Import %s: %s', $id, $name))
        ->setURI($uri);

      if ($import->getIsDisabled()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

}
