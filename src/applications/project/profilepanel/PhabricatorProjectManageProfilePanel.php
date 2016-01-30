<?php

final class PhabricatorProjectManageProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.manage';

  public function getPanelTypeName() {
    return pht('Manage Project');
  }

  private function getDefaultName() {
    return pht('Manage');
  }

  public function canHidePanel(
    PhabricatorProfilePanelConfiguration $config) {
    return false;
  }

  public function canMakeDefault(
    PhabricatorProfilePanelConfiguration $config) {
    return true;
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

    $project = $config->getProfileObject();

    $id = $project->getID();

    $name = $this->getDisplayName($config);
    $icon = 'fa-gears';
    $href = "/project/manage/{$id}/";

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
