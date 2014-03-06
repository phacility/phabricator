<?php

final class PhabricatorProjectPHIDTypeColumn extends PhabricatorPHIDType {

  const TYPECONST = 'PCOL';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Project Column');
  }

  public function newObject() {
    return new PhabricatorProjectColumn();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorProjectColumnQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $column = $objects[$phid];

      $handle->setName($column->getDisplayName());
      $handle->setURI('/project/board/'.$column->getProject()->getID().'/');
    }
  }

}
