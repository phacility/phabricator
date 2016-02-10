<?php

final class PhabricatorProjectSubprojectsProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.subprojects';

  public function getPanelTypeName() {
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
