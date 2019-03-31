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

  public function getMenuItemTypeIcon() {
    return 'fa-file-text-o';
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
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

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $project = $config->getProfileObject();

    $id = $project->getID();
    $name = $project->getName();
    $icon = $project->getDisplayIconIcon();

    $uri = "/project/profile/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
