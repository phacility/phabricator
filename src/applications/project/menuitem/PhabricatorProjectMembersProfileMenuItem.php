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

    $name = $this->getDisplayName($config);
    $icon = 'fa-group';
    $href = "/project/members/{$id}/";

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
