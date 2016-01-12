<?php

final class PhabricatorProfilePanelPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'PANL';

  public function getTypeName() {
    return pht('Profile Panel');
  }

  public function newObject() {
    return new PhabricatorProfilePanelConfiguration();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $object_query,
    array $phids) {
    return id(new PhabricatorProfilePanelConfigurationQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $config = $objects[$phid];

      $handle->setName(pht('Profile Panel'));
      $handle->setURI($config->getURI());
    }
  }

}
