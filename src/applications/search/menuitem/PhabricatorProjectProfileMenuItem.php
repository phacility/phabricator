<?php

final class PhabricatorProjectProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'project';
  const FIELD_PROJECT = 'project';

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

  public function willGetMenuItemViewList(array $items) {
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

    $default = $project->getName();
    return $this->getNameFromConfig($config, $default);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorDatasourceEditField())
        ->setKey(self::FIELD_PROJECT)
        ->setLabel(pht('Project'))
        ->setIsRequired(true)
        ->setDatasource(new PhabricatorProjectDatasource())
        ->setSingleValue($config->getMenuItemProperty('project')),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getNameFromConfig($config)),
    );
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $project = $this->getProject();
    if (!$project) {
      return array();
    }

    $picture = $project->getProfileImageURI();
    $name = $this->getDisplayName($config);
    $uri = $project->getURI();

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIconImage($picture);

    return array(
      $item,
    );
  }

  public function validateTransactions(
    PhabricatorProfileMenuItemConfiguration $config,
    $field_key,
    $value,
    array $xactions) {

    $viewer = $this->getViewer();
    $errors = array();

    if ($field_key == self::FIELD_PROJECT) {
      if ($this->isEmptyTransaction($value, $xactions)) {
       $errors[] = $this->newRequiredError(
         pht('You must choose a project.'),
         $field_key);
      }

      foreach ($xactions as $xaction) {
        $new = $xaction['new'];

        if (!$new) {
          continue;
        }

        if ($new === $value) {
          continue;
        }

        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($new))
          ->execute();
        if (!$projects) {
          $errors[] = $this->newInvalidError(
            pht(
              'Project "%s" is not a valid project which you have '.
              'permission to see.',
              $new),
            $xaction['xaction']);
        }
      }
    }

    return $errors;
  }

}
