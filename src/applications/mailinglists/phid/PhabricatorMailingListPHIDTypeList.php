<?php

final class PhabricatorMailingListPHIDTypeList extends PhabricatorPHIDType {

  const TYPECONST = 'MLST';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Mailing List');
  }

  public function newObject() {
    return new PhabricatorMetaMTAMailingList();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorMailingListQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $list = $objects[$phid];

      $handle->setName($list->getName());
      $handle->setURI($list->getURI());
    }
  }

}
