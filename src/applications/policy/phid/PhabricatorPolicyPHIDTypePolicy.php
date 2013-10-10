<?php

final class PhabricatorPolicyPHIDTypePolicy
  extends PhabricatorPHIDType {

  const TYPECONST = 'PLCY';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Policy');
  }

  public function newObject() {
    return new PhabricatorPolicy();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPolicyQuery())
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
      $policy = $objects[$phid];

      $handle->setName($policy->getName());
      $handle->setURI($policy->getHref());
    }
  }

  public function canLoadNamedObject($name) {
    return false;
  }

}
