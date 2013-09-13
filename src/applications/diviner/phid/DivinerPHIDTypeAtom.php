<?php

final class DivinerPHIDTypeAtom extends PhabricatorPHIDType {

  const TYPECONST = 'ATOM';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Atom');
  }

  public function newObject() {
    return new DivinerLiveSymbol();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DivinerAtomQuery())
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
      $atom = $objects[$phid];

      $handle->setName($atom->getTitle());
      $handle->setURI($atom->getName());
    }
  }

}
