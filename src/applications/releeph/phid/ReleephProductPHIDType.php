<?php

final class ReleephProductPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'REPR';

  public function getTypeName() {
    return pht('Releeph Product');
  }

  public function newObject() {
    return new ReleephProject();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new ReleephProductQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $product = $objects[$phid];

      $handle->setName($product->getName());
      $handle->setURI($product->getURI());
    }
  }

}
