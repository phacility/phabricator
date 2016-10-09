<?php

final class PhabricatorCalendarExportPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CEXP';

  public function getTypeName() {
    return pht('Calendar Export');
  }

  public function newObject() {
    return new PhabricatorCalendarExport();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorCalendarExportQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $export = $objects[$phid];

      $id = $export->getID();
      $name = $export->getName();
      $uri = $export->getURI();

      $handle
        ->setName($name)
        ->setFullName(pht('Calendar Export %s: %s', $id, $name))
        ->setURI($uri);

      if ($export->getIsDisabled()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

}
