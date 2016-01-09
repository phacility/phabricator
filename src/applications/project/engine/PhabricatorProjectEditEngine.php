<?php

final class PhabricatorProjectEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'projects.project';

  private $parentProject;
  private $milestoneProject;

  public function setParentProject(PhabricatorProject $parent_project) {
    $this->parentProject = $parent_project;
    return $this;
  }

  public function getParentProject() {
    return $this->parentProject;
  }

  public function setMilestoneProject(PhabricatorProject $milestone_project) {
    $this->milestoneProject = $milestone_project;
    return $this;
  }

  public function getMilestoneProject() {
    return $this->milestoneProject;
  }

  public function getEngineName() {
    return pht('Projects');
  }

  public function getSummaryHeader() {
    return pht('Configure Project Forms');
  }

  public function getSummaryText() {
    return pht('Configure forms for creating projects.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function newEditableObject() {
    $project = PhabricatorProject::initializeNewProject($this->getViewer());

    $milestone = $this->getMilestoneProject();
    if ($milestone) {
      $default_name = pht(
        'Milestone %s',
        new PhutilNumber($milestone->loadNextMilestoneNumber()));
      $project->setName($default_name);
    }

    return $project;
  }

  protected function newObjectQuery() {
    return id(new PhabricatorProjectQuery())
      ->needSlugs(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Project');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Project');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getObjectCreateCancelURI($object) {
    $parent = $this->getParentProject();
    if ($parent) {
      $id = $parent->getID();
      return "/project/subprojects/{$id}/";
    }

    $milestone = $this->getMilestoneProject();
    if ($milestone) {
      $id = $milestone->getID();
      return "/project/milestones/{$id}/";
    }

    return parent::getObjectCreateCancelURI($object);
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      ProjectCreateProjectsCapability::CAPABILITY);
  }

  protected function willConfigureFields($object, array $fields) {
    $is_milestone = ($this->getMilestoneProject() || $object->isMilestone());

    $unavailable = array(
      PhabricatorTransactions::TYPE_VIEW_POLICY,
      PhabricatorTransactions::TYPE_EDIT_POLICY,
      PhabricatorTransactions::TYPE_JOIN_POLICY,
      PhabricatorProjectTransaction::TYPE_ICON,
      PhabricatorProjectTransaction::TYPE_COLOR,
    );
    $unavailable = array_fuse($unavailable);

    if ($is_milestone) {
      foreach ($fields as $key => $field) {
        $xaction_type = $field->getTransactionType();
        if (isset($unavailable[$xaction_type])) {
          unset($fields[$key]);
        }
      }
    }

    return $fields;
  }

  protected function newBuiltinEngineConfigurations() {
    $configuration = head(parent::newBuiltinEngineConfigurations());

    // TODO: This whole method is clumsy, and the ordering for the custom
    // field is especially clumsy. Maybe try to make this more natural to
    // express.

    $configuration
      ->setFieldOrder(
        array(
          'parent',
          'milestone',
          'name',
          'std:project:internal:description',
          'icon',
          'color',
          'slugs',
          'subscriberPHIDs',
        ));

    return array(
      $configuration,
    );
  }

  protected function buildCustomEditFields($object) {
    $slugs = mpull($object->getSlugs(), 'getSlug');
    $slugs = array_fuse($slugs);
    unset($slugs[$object->getPrimarySlug()]);
    $slugs = array_values($slugs);

    $milestone = $this->getMilestoneProject();
    $parent = $this->getParentProject();

    if ($parent) {
      $parent_phid = $parent->getPHID();
    } else {
      $parent_phid = null;
    }

    if ($milestone) {
      $milestone_phid = $milestone->getPHID();
    } else {
      $milestone_phid = null;
    }

    return array(
      id(new PhabricatorHandlesEditField())
        ->setKey('parent')
        ->setLabel(pht('Parent'))
        ->setDescription(pht('Create a subproject of an existing project.'))
        ->setConduitDescription(
          pht('Choose a parent project to create a subproject beneath.'))
        ->setConduitTypeDescription(pht('PHID of the parent project.'))
        ->setAliases(array('parentPHID'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_PARENT)
        ->setHandleParameterType(new AphrontPHIDHTTPParameterType())
        ->setSingleValue($parent_phid)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false)
        ->setIsLocked(true),
      id(new PhabricatorHandlesEditField())
        ->setKey('milestone')
        ->setLabel(pht('Milestone Of'))
        ->setDescription(pht('Parent project to create a milestone for.'))
        ->setConduitDescription(
          pht('Choose a parent project to create a new milestone for.'))
        ->setConduitTypeDescription(pht('PHID of the parent project.'))
        ->setAliases(array('milestonePHID'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_MILESTONE)
        ->setHandleParameterType(new AphrontPHIDHTTPParameterType())
        ->setSingleValue($milestone_phid)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false)
        ->setIsLocked(true),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setDescription(pht('Project name.'))
        ->setConduitDescription(pht('Rename the project'))
        ->setConduitTypeDescription(pht('New project name.'))
        ->setValue($object->getName()),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_ICON)
        ->setIconSet(new PhabricatorProjectIconSet())
        ->setDescription(pht('Project icon.'))
        ->setConduitDescription(pht('Change the project icon.'))
        ->setConduitTypeDescription(pht('New project icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('color')
        ->setLabel(pht('Color'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_COLOR)
        ->setOptions(PhabricatorProjectIconSet::getColorMap())
        ->setDescription(pht('Project tag color.'))
        ->setConduitDescription(pht('Change the project tag color.'))
        ->setConduitTypeDescription(pht('New project tag color.'))
        ->setValue($object->getColor()),
      id(new PhabricatorStringListEditField())
        ->setKey('slugs')
        ->setLabel(pht('Additional Hashtags'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_SLUGS)
        ->setDescription(pht('Additional project slugs.'))
        ->setConduitDescription(pht('Change project slugs.'))
        ->setConduitTypeDescription(pht('New list of slugs.'))
        ->setValue($slugs),
    );
  }

}
