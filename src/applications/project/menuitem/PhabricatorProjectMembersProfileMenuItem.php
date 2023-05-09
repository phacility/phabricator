<?php

final class PhabricatorProjectMembersProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.members';

  public function getMenuItemTypeName() {
    return pht('Project Members');
  }

  private function getDefaultName() {
    return pht('Members');
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $default = $this->getDefaultName();
    return $this->getNameFromConfig($config, $default);
  }

  public function getMenuItemTypeIcon() {
    return 'fa-users';
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

    $name = $this->getDisplayName($config);
    $icon = 'fa-group';
    $uri = "/project/members/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
