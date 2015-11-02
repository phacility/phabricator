<?php

final class PhortuneProductPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PDCT';

  public function getTypeName() {
    return pht('Phortune Product');
  }

  public function newObject() {
    return new PhortuneProduct();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneProductQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $product = $objects[$phid];

      $id = $product->getID();

      $handle->setName(pht('Product %d', $id));
      $handle->setURI("/phortune/product/{$id}/");
    }
  }

}
