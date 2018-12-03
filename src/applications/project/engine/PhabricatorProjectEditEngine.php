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

  public function isDefaultQuickCreateEngine() {
    return true;
  }

  public function getQuickCreateOrderVector() {
    return id(new PhutilSortVector())->addInt(200);
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
    $parent = nonempty($this->parentProject, $this->milestoneProject);

    return PhabricatorProject::initializeNewProject(
      $this->getViewer(),
      $parent);
  }

  protected function newObjectQuery() {
    return id(new PhabricatorProjectQuery())
      ->needSlugs(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Project');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Project: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Project');
  }

  protected function getObjectName() {
    return pht('Project');
  }

  protected function getObjectViewURI($object) {
    if ($this->getIsCreate()) {
      return $object->getURI();
    } else {
      $id = $object->getID();
      return "/project/manage/{$id}/";
    }
  }

  protected function getObjectCreateCancelURI($object) {
    $parent = $this->getParentProject();
    $milestone = $this->getMilestoneProject();

    if ($parent || $milestone) {
      $id = nonempty($parent, $milestone)->getID();
      return "/project/subprojects/{$id}/";
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
      PhabricatorTransactions::TYPE_SPACE,
      PhabricatorProjectIconTransaction::TRANSACTIONTYPE,
      PhabricatorProjectColorTransaction::TRANSACTIONTYPE,
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
          'milestone.previous',
          'name',
          'std:project:internal:description',
          'icon',
          'color',
          'slugs',
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

    $previous_milestone_phid = null;
    if ($milestone) {
      $milestone_phid = $milestone->getPHID();

      // Load the current milestone so we can show the user a hint about what
      // it was called, so they don't have to remember if the next one should
      // be "Sprint 287" or "Sprint 278".

      $number = ($milestone->loadNextMilestoneNumber() - 1);
      if ($number > 0) {
        $previous_milestone = id(new PhabricatorProjectQuery())
          ->setViewer($this->getViewer())
          ->withParentProjectPHIDs(array($milestone->getPHID()))
          ->withIsMilestone(true)
          ->withMilestoneNumberBetween($number, $number)
          ->executeOne();
        if ($previous_milestone) {
          $previous_milestone_phid = $previous_milestone->getPHID();
        }
      }
    } else {
      $milestone_phid = null;
    }

    $fields = array(
      id(new PhabricatorHandlesEditField())
        ->setKey('parent')
        ->setLabel(pht('Parent'))
        ->setDescription(pht('Create a subproject of an existing project.'))
        ->setConduitDescription(
          pht('Choose a parent project to create a subproject beneath.'))
        ->setConduitTypeDescription(pht('PHID of the parent project.'))
        ->setAliases(array('parentPHID'))
        ->setTransactionType(
            PhabricatorProjectParentTransaction::TRANSACTIONTYPE)
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
        ->setTransactionType(
            PhabricatorProjectMilestoneTransaction::TRANSACTIONTYPE)
        ->setHandleParameterType(new AphrontPHIDHTTPParameterType())
        ->setSingleValue($milestone_phid)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false)
        ->setIsLocked(true),
      id(new PhabricatorHandlesEditField())
        ->setKey('milestone.previous')
        ->setLabel(pht('Previous Milestone'))
        ->setSingleValue($previous_milestone_phid)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false)
        ->setIsLocked(true),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setTransactionType(PhabricatorProjectNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setDescription(pht('Project name.'))
        ->setConduitDescription(pht('Rename the project'))
        ->setConduitTypeDescription(pht('New project name.'))
        ->setValue($object->getName()),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setTransactionType(
            PhabricatorProjectIconTransaction::TRANSACTIONTYPE)
        ->setIconSet(new PhabricatorProjectIconSet())
        ->setDescription(pht('Project icon.'))
        ->setConduitDescription(pht('Change the project icon.'))
        ->setConduitTypeDescription(pht('New project icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('color')
        ->setLabel(pht('Color'))
        ->setTransactionType(
            PhabricatorProjectColorTransaction::TRANSACTIONTYPE)
        ->setOptions(PhabricatorProjectIconSet::getColorMap())
        ->setDescription(pht('Project tag color.'))
        ->setConduitDescription(pht('Change the project tag color.'))
        ->setConduitTypeDescription(pht('New project tag color.'))
        ->setValue($object->getColor()),
      id(new PhabricatorStringListEditField())
        ->setKey('slugs')
        ->setLabel(pht('Additional Hashtags'))
        ->setTransactionType(
            PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Additional project slugs.'))
        ->setConduitDescription(pht('Change project slugs.'))
        ->setConduitTypeDescription(pht('New list of slugs.'))
        ->setValue($slugs),
    );

    $can_edit_members = (!$milestone) &&
                        (!$object->isMilestone()) &&
                        (!$object->getHasSubprojects());

    if ($can_edit_members) {

      // Show this on the web UI when creating a project, but not when editing
      // one. It is always available via Conduit.
      $show_field = (bool)$this->getIsCreate();

      $members_field = id(new PhabricatorUsersEditField())
        ->setKey('members')
        ->setAliases(array('memberPHIDs'))
        ->setLabel(pht('Initial Members'))
        ->setIsFormField($show_field)
        ->setUseEdgeTransactions(true)
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorProjectProjectHasMemberEdgeType::EDGECONST)
        ->setDescription(pht('Initial project members.'))
        ->setConduitDescription(pht('Set project members.'))
        ->setConduitTypeDescription(pht('New list of members.'))
        ->setValue(array());

      $members_field->setViewer($this->getViewer());

      $edit_add = $members_field->getConduitEditType('members.add')
        ->setConduitDescription(pht('Add members.'));

      $edit_set = $members_field->getConduitEditType('members.set')
        ->setConduitDescription(
          pht('Set members, overwriting the current value.'));

      $edit_rem = $members_field->getConduitEditType('members.remove')
        ->setConduitDescription(pht('Remove members.'));

      $fields[] = $members_field;
    }

    return $fields;

  }

}
