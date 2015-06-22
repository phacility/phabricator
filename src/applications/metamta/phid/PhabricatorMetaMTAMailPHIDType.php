<?php

final class PhabricatorMetaMTAMailPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'MTAM';

  public function getTypeName() {
    return pht('MetaMTA Mail');
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

  public function newObject() {
    return new PhabricatorMetaMTAMail();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorMetaMTAMailQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $mail = $objects[$phid];

      $id = $mail->getID();
      $name = pht('Mail %d', $id);

      $handle
        ->setName($name)
        ->setFullName($name);
    }
  }
}
