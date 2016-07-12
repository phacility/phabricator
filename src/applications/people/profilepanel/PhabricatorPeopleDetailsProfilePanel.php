<?php

final class PhabricatorPeopleDetailsProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'people.details';

  public function getPanelTypeName() {
    return pht('User Details');
  }

  private function getDefaultName() {
    return pht('User Details');
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

    $picture = $user->getProfileImageURI();
    $name = $user->getUsername();
    $href = urisprintf(
      '/p/%s/',
      $user->getUsername());

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setProfileImage($picture);

    return array(
      $item,
    );
  }

}
