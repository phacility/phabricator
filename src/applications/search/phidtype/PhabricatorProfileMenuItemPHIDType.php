<?php

final class PhabricatorProfileMenuItemPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'PANL';

  public function getTypeName() {
    return pht('Profile Menu Item');
  }

  public function newObject() {
    return new PhabricatorProfileMenuItemConfiguration();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $object_query,
    array $phids) {
    return id(new PhabricatorProfileMenuItemConfigurationQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $config = $objects[$phid];

      $handle->setName(pht('Profile Menu Item'));
    }
  }

}
