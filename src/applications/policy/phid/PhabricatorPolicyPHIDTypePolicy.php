<?php

final class PhabricatorPolicyPHIDTypePolicy extends PhabricatorPHIDType {

  const TYPECONST = 'PLCY';

  public function getTypeName() {
    return pht('Policy');
  }

  public function newObject() {
    return new PhabricatorPolicy();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPolicyQuery())
      ->withPHIDs($phids);
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

}
