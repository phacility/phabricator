<?php

final class PassphraseCredentialPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CDTL';

  public function getTypeName() {
    return pht('Passphrase Credential');
  }

  public function newObject() {
    return new PassphraseCredential();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPassphraseApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PassphraseCredentialQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $credential = $objects[$phid];
      $id = $credential->getID();
      $name = $credential->getName();

      $handle->setName("K{$id}");
      $handle->setFullName("K{$id} {$name}");
      $handle->setURI("/K{$id}");

      if ($credential->getIsDestroyed()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^K\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new PassphraseCredentialQuery())
      ->setViewer($query->getViewer())
      ->withIDs(array_keys($id_map))
      ->execute();

    $results = array();
    foreach ($objects as $id => $object) {
      foreach (idx($id_map, $id, array()) as $name) {
        $results[$name] = $object;
      }
    }

    return $results;
  }

}
