<?php

final class PhabricatorUserPreferencesPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PSET';

  public function getTypeName() {
    return pht('Settings');
  }

  public function newObject() {
    return new PhabricatorUserPreferences();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorSettingsApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorUserPreferencesQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $preferences = $objects[$phid];
      $handle->setName(pht('Settings %d', $preferences->getID()));
    }
  }

}
