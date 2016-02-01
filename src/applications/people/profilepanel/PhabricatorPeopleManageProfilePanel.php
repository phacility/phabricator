<?php

final class PhabricatorPeopleManageProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'people.manage';

  public function getPanelTypeName() {
    return pht('Mangage User');
  }

  private function getDefaultName() {
    return pht('Manage');
  }

  public function canHidePanel(
    PhabricatorProfilePanelConfiguration $config) {
    return false;
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {
    $name = $config->getPanelProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setPlaceholder($this->getDefaultName())
        ->setValue($config->getPanelProperty('name')),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $user = $config->getProfileObject();
    $id = $user->getID();

    $item = $this->newItem()
      ->setHref("/people/manage/{$id}/")
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-gears');

    return array(
      $item,
    );
  }

}
