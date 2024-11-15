<?php

final class PhabricatorRoleReportsProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'role.reports';

  public function getMenuItemTypeName() {
    return pht('Role Reports');
  }

  private function getDefaultName() {
    return pht('Reports (Prototype)');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-area-chart';
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function shouldEnableForObject($object) {
    $viewer = $this->getViewer();

    if (!PhabricatorEnv::getEnvConfig('phabricator.show-prototypes')) {
      return false;
    }

    $class = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return false;
    }

    $class = 'PhabricatorFactApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return false;
    }

    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if ($name !== null && $name !== '') {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setPlaceholder($this->getDefaultName())
        ->setValue($config->getMenuItemProperty('name')),
    );
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {
    $role = $config->getProfileObject();

    $id = $role->getID();
    $uri = $role->getReportsURI();
    $name = $this->getDisplayName($config);

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon('fa-area-chart');

    return array(
      $item,
    );
  }

}
