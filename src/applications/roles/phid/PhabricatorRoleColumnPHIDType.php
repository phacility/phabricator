<?php

final class PhabricatorRoleColumnPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'RCOL';

  public function getTypeName() {
    return pht('Role Column');
  }

  public function getTypeIcon() {
    return 'fa-columns bluegrey';
  }

  public function newObject() {
    return new PhabricatorRoleColumn();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRoleColumnQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $column = $objects[$phid];

      $handle->setName($column->getDisplayName());
      $handle->setURI($column->getWorkboardURI());

      if ($column->isHidden()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

}
