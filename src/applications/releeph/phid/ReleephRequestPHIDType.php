<?php

final class ReleephRequestPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'RERQ';

  public function getTypeName() {
    return pht('Releeph Request');
  }

  public function newObject() {
    return new ReleephRequest();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ReleephRequestQuery())
      ->withPHIDs($phids);
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

}
