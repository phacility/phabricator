<?php

final class PhabricatorProjectWorkboardProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project.workboard';

  public function getMenuItemTypeName() {
    return pht('Project Workboard');
  }

  private function getDefaultName() {
    return pht('Workboard');
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function shouldEnableForObject($object) {
    $viewer = $this->getViewer();

    // Workboards are only available if Maniphest is installed.
    $class = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
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

    $has_workboard = $project->getHasWorkboard();

    $id = $project->getID();
    $href = "/project/board/{$id}/";
    $name = $this->getDisplayName($config);

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setDisabled(!$has_workboard)
      ->setIcon('fa-columns');

    return array(
      $item,
    );
  }

}
