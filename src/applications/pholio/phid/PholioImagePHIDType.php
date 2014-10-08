<?php

final class PholioImagePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PIMG';

  public function getTypeName() {
    return pht('Image');
  }

  public function newObject() {
    return new PholioImage();
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

      $id = $image->getID();
      $mock_id = $image->getMockID();
      $name = $image->getName();

      $handle->setURI("/M{$mock_id}/{$id}/");
      $handle->setName($name);
      $handle->setFullName($name);
    }
  }

}
