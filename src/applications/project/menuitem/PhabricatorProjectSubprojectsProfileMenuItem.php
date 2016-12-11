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

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $project = $config->getProfileObject();

    $has_children = ($project->getHasSubprojects()) ||
                    ($project->getHasMilestones());

    $id = $project->getID();

    $name = $this->getDisplayName($config);
    $icon = 'fa-sitemap';
    $href = "/project/subprojects/{$id}/";

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setDisabled(!$has_children)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
