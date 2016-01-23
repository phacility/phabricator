<?php

final class PhabricatorProjectDetailsProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.details';

  public function getPanelTypeName() {
    return pht('Project Details');
  }

  private function getDefaultName() {
    return pht('Project Details');
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
