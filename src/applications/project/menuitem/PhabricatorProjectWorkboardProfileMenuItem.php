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

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $default = $this->getDefaultName();
    return $this->getNameFromConfig($config, $default);
  }

  public function getMenuItemTypeIcon() {
    return 'fa-columns';
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
    $uri = $project->getWorkboardURI();
    $name = $this->getDisplayName($config);

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon('fa-columns');

    return array(
      $item,
    );
  }

}
