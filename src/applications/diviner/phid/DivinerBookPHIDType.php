<?php

final class DivinerBookPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'BOOK';

  public function getTypeName() {
    return pht('Diviner Book');
  }

  public function newObject() {
    return new DivinerLiveBook();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DivinerBookQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $book = $objects[$phid];

      $name = $book->getName();

      $handle
        ->setName($book->getShortTitle())
        ->setFullName($book->getTitle())
        ->setURI("/book/{$name}/");
    }
  }

}
