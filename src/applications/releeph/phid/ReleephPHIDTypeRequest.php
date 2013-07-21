<?php

final class ReleephPHIDTypeRequest extends PhabricatorPHIDType {

  const TYPECONST = 'RERQ';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Releeph Request');
  }

  public function newObject() {
    return new ReleephRequest();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ReleephRequestQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $request = $objects[$phid];

      $id = $request->getID();
      $title = $request->getSummaryForDisplay();

      $handle->setURI("/RQ{$id}");
      $handle->setName($title);
      $handle->setFullName("RQ{$id}: {$title}");
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
