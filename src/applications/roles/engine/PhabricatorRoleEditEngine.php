<?php

final class PhabricatorRoleEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'roles.role';

  private $parentRole;
  private $milestoneRole;

  public function setParentRole(PhabricatorRole $parent_role) {
    $this->parentRole = $parent_role;
    return $this;
  }

  public function getParentRole() {
    return $this->parentRole;
  }

  public function setMilestoneRole(PhabricatorRole $milestone_role) {
    $this->milestoneRole = $milestone_role;
    return $this;
  }

  public function getMilestoneRole() {
    return $this->milestoneRole;
  }

  public function isDefaultQuickCreateEngine() {
    return true;
  }

  public function getQuickCreateOrderVector() {
    return id(new PhutilSortVector())->addInt(200);
  }

  public function getEngineName() {
    return pht('Roles');
  }

  public function getSummaryHeader() {
    return pht('Configure Role Forms');
  }

  public function getSummaryText() {
    return pht('Configure forms for creating roles.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  protected function newEditableObject() {
    $parent = nonempty($this->parentRole, $this->milestoneRole);

    return PhabricatorRole::initializeNewRole(
      $this->getViewer(),
      $parent);
  }

  protected function newObjectQuery() {
    return id(new PhabricatorRoleQuery())
      ->needSlugs(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Role');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Role: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Role');
  }

  protected function getObjectName() {
    return pht('Role');
  }

  protected function getObjectViewURI($object) {
    if ($this->getIsCreate()) {
      return $object->getURI();
    } else {
      $id = $object->getID();
      return "/role/manage/{$id}/";
    }
  }

  protected function getObjectCreateCancelURI($object) {
    $parent = $this->getParentRole();
    $milestone = $this->getMilestoneRole();

    if ($parent || $milestone) {
      $id = nonempty($parent, $milestone)->getID();
      return "/role/subroles/{$id}/";
    }

    return parent::getObjectCreateCancelURI($object);
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      RoleCreateRolesCapability::CAPABILITY);
  }

  protected function willConfigureFields($object, array $fields) {
    $is_milestone = ($this->getMilestoneRole() || $object->isMilestone());

    $unavailable = array(
      PhabricatorTransactions::TYPE_VIEW_POLICY,
      PhabricatorTransactions::TYPE_EDIT_POLICY,
      PhabricatorTransactions::TYPE_JOIN_POLICY,
      PhabricatorTransactions::TYPE_SPACE,
      PhabricatorRoleIconTransaction::TRANSACTIONTYPE,
      PhabricatorRoleColorTransaction::TRANSACTIONTYPE,
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
          'std:role:internal:description',
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

    $milestone = $this->getMilestoneRole();
    $parent = $this->getParentRole();

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
        $previous_milestone = id(new PhabricatorRoleQuery())
          ->setViewer($this->getViewer())
          ->withParentRolePHIDs(array($milestone->getPHID()))
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
        ->setDescription(pht('Create a subrole of an existing role.'))
        ->setConduitDescription(
          pht('Choose a parent role to create a subrole beneath.'))
        ->setConduitTypeDescription(pht('PHID of the parent role.'))
        ->setAliases(array('parentPHID'))
        ->setTransactionType(
            PhabricatorRoleParentTransaction::TRANSACTIONTYPE)
        ->setHandleParameterType(new AphrontPHIDHTTPParameterType())
        ->setSingleValue($parent_phid)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false)
        ->setIsLocked(true),
      id(new PhabricatorHandlesEditField())
        ->setKey('milestone')
        ->setLabel(pht('Milestone Of'))
        ->setDescription(pht('Parent role to create a milestone for.'))
        ->setConduitDescription(
          pht('Choose a parent role to create a new milestone for.'))
        ->setConduitTypeDescription(pht('PHID of the parent role.'))
        ->setAliases(array('milestonePHID'))
        ->setTransactionType(
            PhabricatorRoleMilestoneTransaction::TRANSACTIONTYPE)
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
        ->setTransactionType(PhabricatorRoleNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setDescription(pht('Role name.'))
        ->setConduitDescription(pht('Rename the role'))
        ->setConduitTypeDescription(pht('New role name.'))
        ->setValue($object->getName()),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setTransactionType(
            PhabricatorRoleIconTransaction::TRANSACTIONTYPE)
        ->setIconSet(new PhabricatorRoleIconSet())
        ->setDescription(pht('Role icon.'))
        ->setConduitDescription(pht('Change the role icon.'))
        ->setConduitTypeDescription(pht('New role icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('color')
        ->setLabel(pht('Color'))
        ->setTransactionType(
            PhabricatorRoleColorTransaction::TRANSACTIONTYPE)
        ->setOptions(PhabricatorRoleIconSet::getColorMap())
        ->setDescription(pht('Role tag color.'))
        ->setConduitDescription(pht('Change the role tag color.'))
        ->setConduitTypeDescription(pht('New role tag color.'))
        ->setValue($object->getColor()),
      id(new PhabricatorStringListEditField())
        ->setKey('slugs')
        ->setLabel(pht('Additional Hashtags'))
        ->setTransactionType(
            PhabricatorRoleSlugsTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('Additional role slugs.'))
        ->setConduitDescription(pht('Change role slugs.'))
        ->setConduitTypeDescription(pht('New list of slugs.'))
        ->setValue($slugs),
    );

    $can_edit_members = (!$milestone) &&
                        (!$object->isMilestone()) &&
                        (!$object->getHasSubroles());

    if ($can_edit_members) {

      // Show this on the web UI when creating a role, but not when editing
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
          PhabricatorRoleRoleHasMemberEdgeType::EDGECONST)
        ->setDescription(pht('Initial role members.'))
        ->setConduitDescription(pht('Set role members.'))
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
