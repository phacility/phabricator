<?php

final class PhabricatorProjectProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project';

  private $project;

  public function getMenuItemTypeIcon() {
    return 'fa-briefcase';
  }

  public function getMenuItemTypeName() {
    return pht('Project');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function attachProject($project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    $project = $this->project;
    if (!$project) {
      return null;
    } else if ($project->isArchived()) {
      return null;
    }
    return $project;
  }

  public function willBuildNavigationItems(array $items) {
    $viewer = $this->getViewer();
    $project_phids = array();
    foreach ($items as $item) {
      $project_phids[] = $item->getMenuItemProperty('project');
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($project_phids)
      ->needImages(true)
      ->execute();

    $projects = mpull($projects, null, 'getPHID');
    foreach ($items as $item) {
      $project_phid = $item->getMenuItemProperty('project');
      $project = idx($projects, $project_phid, null);
      $item->getMenuItem()->attachProject($project);
    }
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $project = $this->getProject();
    if (!$project) {
      return pht('(Restricted/Invalid Project)');
    }
    if (strlen($this->getName($config))) {
      return $this->getName($config);
    } else {
      return $project->getName();
    }
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getName($config)),
      id(new PhabricatorDatasourceEditField())
        ->setKey('project')
        ->setLabel(pht('Project'))
        ->setDatasource(new PhabricatorProjectDatasource())
        ->setSingleValue($config->getMenuItemProperty('project')),
    );
  }

  private function getName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('name');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $project = $this->getProject();
    if (!$project) {
      return array();
    }

    $picture = $project->getProfileImageURI();
    $name = $this->getDisplayName($config);
    $href = $project->getURI();

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setProfileImage($picture);

    return array(
      $item,
    );
  }

}
