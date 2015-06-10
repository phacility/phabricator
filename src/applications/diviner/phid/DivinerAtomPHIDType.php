<?php

final class DivinerAtomPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ATOM';

  public function getTypeName() {
    return pht('Diviner Atom');
  }

  public function newObject() {
    return new DivinerLiveSymbol();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DivinerAtomQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $atom = $objects[$phid];

      $book = $atom->getBook()->getName();
      $name = $atom->getName();
      $type = $atom->getType();

      $handle
        ->setName($atom->getName())
        ->setTitle($atom->getTitle())
        ->setURI("/book/{$book}/{$type}/{$name}/")
        ->setStatus($atom->getGraphHash()
          ? PhabricatorObjectHandle::STATUS_OPEN
          : PhabricatorObjectHandle::STATUS_CLOSED);
    }
  }

}
