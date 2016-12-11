<?php

final class PhabricatorProjectDetailsProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.details';

  public function getMenuItemTypeName() {
    return pht('Project Details');
  }

  private function getDefaultName() {
    return pht('Project Details');
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $name = $config->getMenuItemProperty('name');

    if (strlen($name)) {
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

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $project = $config->getProfileObject();

    $id = $project->getID();
    $picture = $project->getProfileImageURI();
    $name = $project->getName();

    $href = "/project/profile/{$id}/";

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setProfileImage($picture);

    return array(
      $item,
    );
  }

}
