<?php

final class ReleephPHIDTypeBranch extends PhabricatorPHIDType {

  const TYPECONST = 'REBR';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Releeph Branch');
  }

  public function newObject() {
    return new ReleephBranch();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ReleephBranchQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $branch = $objects[$phid];

      $handle->setURI($branch->getURI());
      $handle->setName($branch->getBasename());
      $handle->setFullName($branch->getName());
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
