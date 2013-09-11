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

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorMailingListQuery())
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
      $list = $objects[$phid];

      $handle->setName($list->getName());
      $handle->setURI($list->getURI());
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
