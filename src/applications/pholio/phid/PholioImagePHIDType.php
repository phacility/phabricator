<?php

final class PholioImagePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PIMG';

  public function getTypeName() {
    return pht('Image');
  }

  public function newObject() {
    return new PholioImage();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PholioImageQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $image = $objects[$phid];

      $handle
        ->setName($image->getName())
        ->setURI($image->getURI());
    }
  }

}
