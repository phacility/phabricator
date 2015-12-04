<?php

final class ManiphestEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'maniphest.task';

  public function getEngineName() {
    return pht('Maniphest Tasks');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  protected function newEditableObject() {
    return ManiphestTask::initializeNewTask($this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new ManiphestTaskQuery());
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Task');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s %s', $object->getMonogram(), $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Task');
  }

  protected function getCommentViewHeaderText($object) {
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    if (!$is_serious) {
      return pht('Weigh In');
    }

    return parent::getCommentViewHeaderText($object);
  }

  protected function getObjectViewURI($object) {
    return '/'.$object->getMonogram();
  }

  protected function buildCustomEditFields($object) {
    // See T4819.
    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $dup_status = ManiphestTaskStatus::getDuplicateStatus();
    if ($object->getStatus() != $dup_status) {
      unset($status_map[$dup_status]);
    }

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($object->isClosed()) {
      $priority_label = null;
      $owner_label = null;
      $default_status = ManiphestTaskStatus::getDefaultStatus();
    } else {
      $priority_label = pht('Change Priority');
      $owner_label = pht('Assign / Claim');
      $default_status = ManiphestTaskStatus::getDefaultClosedStatus();
    }

    if ($object->getOwnerPHID()) {
      $owner_value = array($object->getOwnerPHID());
    } else {
      $owner_value = array($this->getViewer()->getPHID());
    }

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('title')
        ->setLabel(pht('Title'))
        ->setDescription(pht('Name of the task.'))
        ->setTransactionType(ManiphestTransaction::TYPE_TITLE)
        ->setIsRequired(true)
        ->setValue($object->getTitle()),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setDescription(pht('Status of the task.'))
        ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
        ->setValue($object->getStatus())
        ->setOptions($status_map)
        ->setCommentActionLabel(pht('Change Status'))
        ->setCommentActionDefaultValue($default_status),
      id(new PhabricatorUsersEditField())
        ->setKey('owner')
        ->setAliases(array('ownerPHID', 'assign', 'assigned'))
        ->setLabel(pht('Assigned To'))
        ->setDescription(pht('User who is responsible for the task.'))
        ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
        ->setSingleValue($object->getOwnerPHID())
        ->setCommentActionLabel($owner_label)
        ->setCommentActionDefaultValue($owner_value),
      id(new PhabricatorSelectEditField())
        ->setKey('priority')
        ->setLabel(pht('Priority'))
        ->setDescription(pht('Priority of the task.'))
        ->setTransactionType(ManiphestTransaction::TYPE_PRIORITY)
        ->setValue($object->getPriority())
        ->setOptions($priority_map)
        ->setCommentActionLabel($priority_label),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Task description.'))
        ->setTransactionType(ManiphestTransaction::TYPE_DESCRIPTION)
        ->setValue($object->getDescription()),
    );
  }

  protected function getEditorURI() {
    // TODO: Remove when cutting over.
    return $this->getApplication()->getApplicationURI('editpro/');
  }

}
