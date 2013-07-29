<?php

final class PholioPHIDTypeImage extends PhabricatorPHIDType {

  const TYPECONST = 'PIMG';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Image');
  }

  public function newObject() {
    return new PholioImage();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PholioImageQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
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
