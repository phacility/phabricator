<?php

final class DivinerPHIDTypeBook extends PhabricatorPHIDType {

  const TYPECONST = 'BOOK';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Book');
  }

  public function newObject() {
    return new DivinerLiveBook();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DivinerBookQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $book = $objects[$phid];

      $name = $book->getName();

      $handle->setName($book->getShortTitle());
      $handle->setFullName($book->getTitle());
      $handle->setURI("/diviner/book/{$name}/");
    }
  }

}
