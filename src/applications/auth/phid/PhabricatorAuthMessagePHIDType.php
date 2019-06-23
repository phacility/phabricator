<?php

final class PhabricatorAuthMessagePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'AMSG';

  public function getTypeName() {
    return pht('Auth Message');
  }

  public function newObject() {
    return new PhabricatorAuthMessage();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorAuthMessageQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $message = $objects[$phid];
      $handle->setURI($message->getURI());
    }
  }

}
