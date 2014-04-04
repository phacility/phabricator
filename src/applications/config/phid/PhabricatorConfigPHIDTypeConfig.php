<?php

final class PhabricatorConfigPHIDTypeConfig extends PhabricatorPHIDType {

  const TYPECONST = 'CONF';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Config');
  }

  public function newObject() {
    return new PhabricatorConfigEntry();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorConfigEntryQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $entry = $objects[$phid];

      $key = $entry->getConfigKey();

      $handle->setName($key);
      $handle->setURI("/config/edit/{$key}/");
    }
  }

}
