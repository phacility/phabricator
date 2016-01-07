<?php

final class PhabricatorEditEngineConfigurationPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'FORM';

  public function getTypeName() {
    return pht('Edit Configuration');
  }

  public function newObject() {
    return new PhabricatorEditEngineConfiguration();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorTransactionsApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $object_query,
    array $phids) {
    return id(new PhabricatorEditEngineConfigurationQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $config = $objects[$phid];

      $id = $config->getID();
      $name = $config->getName();

      $handle->setName($name);
      $handle->setURI($config->getURI());
    }
  }

}
