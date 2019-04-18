<?php

final class PhabricatorProjectSubprojectsProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.subprojects';

  public function getMenuItemTypeName() {
    return pht('Project Subprojects');
  }

  private function getDefaultName() {
    return pht('Subprojects');
  }

  public function getMenuItemTypeIcon() {
    return 'fa-sitemap';
  }

  public function shouldEnableForObject($object) {
    if ($object->isMilestone()) {
      return false;
    }

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

    $name = $this->getDisplayName($config);
    $icon = 'fa-sitemap';
    $uri = "/project/subprojects/{$id}/";

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
