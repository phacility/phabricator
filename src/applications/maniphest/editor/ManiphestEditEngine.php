<?php

final class ManiphestEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'maniphest.task';

  public function getEngineName() {
    return pht('Maniphest Tasks');
  }

  public function getSummaryHeader() {
    return pht('Configure Maniphest Task Forms');
  }

  public function getSummaryText() {
    return pht('Configure how users create and edit tasks.');
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

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('task/edit/');
  }

  protected function getCommentViewHeaderText($object) {
    return pht('Weigh In');
  }

  protected function getCommentViewButtonText($object) {
    return pht('Set Sail for Adventure');
  }

  protected function getObjectViewURI($object) {
    return '/'.$object->getMonogram();
  }

  protected function buildCustomEditFields($object) {
    $status_map = $this->getTaskStatusMap($object);
    $priority_map = $this->getTaskPriorityMap($object);

    if ($object->isClosed()) {
      $default_status = ManiphestTaskStatus::getDefaultStatus();
    } else {
      $default_status = ManiphestTaskStatus::getDefaultClosedStatus();
    }

    if ($object->getOwnerPHID()) {
      $owner_value = array($object->getOwnerPHID());
    } else {
      $owner_value = array($this->getViewer()->getPHID());
    }

    $fields = array(
      id(new PhabricatorHandlesEditField())
        ->setKey('parent')
        ->setLabel(pht('Parent Task'))
        ->setDescription(pht('Task to make this a subtask of.'))
        ->setConduitDescription(pht('Create as a subtask of another task.'))
        ->setConduitTypeDescription(pht('PHID of the parent task.'))
        ->setAliases(array('parentPHID'))
        ->setTransactionType(ManiphestTransaction::TYPE_PARENT)
        ->setHandleParameterType(new ManiphestTaskListHTTPParameterType())
        ->setSingleValue(null)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false),
      id(new PhabricatorHandlesEditField())
        ->setKey('column')
        ->setLabel(pht('Column'))
        ->setDescription(pht('Workboard column to create this task into.'))
        ->setConduitDescription(pht('Create into a workboard column.'))
        ->setConduitTypeDescription(pht('PHID of workboard column.'))
        ->setAliases(array('columnPHID'))
        ->setTransactionType(ManiphestTransaction::TYPE_COLUMN)
        ->setSingleValue(null)
        ->setIsInvisible(true)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false),
      id(new PhabricatorTextEditField())
        ->setKey('title')
        ->setLabel(pht('Title'))
        ->setDescription(pht('Name of the task.'))
        ->setConduitDescription(pht('Rename the task.'))
        ->setConduitTypeDescription(pht('New task name.'))
        ->setTransactionType(ManiphestTransaction::TYPE_TITLE)
        ->setIsRequired(true)
        ->setValue($object->getTitle()),
      id(new PhabricatorUsersEditField())
        ->setKey('owner')
        ->setAliases(array('ownerPHID', 'assign', 'assigned'))
        ->setLabel(pht('Assigned To'))
        ->setDescription(pht('User who is responsible for the task.'))
        ->setConduitDescription(pht('Reassign the task.'))
        ->setConduitTypeDescription(
          pht('New task owner, or `null` to unassign.'))
        ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
        ->setIsCopyable(true)
        ->setSingleValue($object->getOwnerPHID())
        ->setCommentActionLabel(pht('Assign / Claim'))
        ->setCommentActionValue($owner_value),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setDescription(pht('Status of the task.'))
        ->setConduitDescription(pht('Change the task status.'))
        ->setConduitTypeDescription(pht('New task status constant.'))
        ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
        ->setIsCopyable(true)
        ->setValue($object->getStatus())
        ->setOptions($status_map)
        ->setCommentActionLabel(pht('Change Status'))
        ->setCommentActionValue($default_status),
      id(new PhabricatorSelectEditField())
        ->setKey('priority')
        ->setLabel(pht('Priority'))
        ->setDescription(pht('Priority of the task.'))
        ->setConduitDescription(pht('Change the priority of the task.'))
        ->setConduitTypeDescription(pht('New task priority constant.'))
        ->setTransactionType(ManiphestTransaction::TYPE_PRIORITY)
        ->setIsCopyable(true)
        ->setValue($object->getPriority())
        ->setOptions($priority_map)
        ->setCommentActionLabel(pht('Change Priority')),
    );

    if (ManiphestTaskPoints::getIsEnabled()) {
      $points_label = ManiphestTaskPoints::getPointsLabel();
      $action_label = ManiphestTaskPoints::getPointsActionLabel();

      $fields[] = id(new PhabricatorPointsEditField())
        ->setKey('points')
        ->setLabel($points_label)
        ->setDescription(pht('Point value of the task.'))
        ->setConduitDescription(pht('Change the task point value.'))
        ->setConduitTypeDescription(pht('New task point value.'))
        ->setTransactionType(ManiphestTransaction::TYPE_POINTS)
        ->setIsCopyable(true)
        ->setValue($object->getPoints())
        ->setCommentActionLabel($action_label);
    }

    $fields[] = id(new PhabricatorRemarkupEditField())
      ->setKey('description')
      ->setLabel(pht('Description'))
      ->setDescription(pht('Task description.'))
      ->setConduitDescription(pht('Update the task description.'))
      ->setConduitTypeDescription(pht('New task description.'))
      ->setTransactionType(ManiphestTransaction::TYPE_DESCRIPTION)
      ->setValue($object->getDescription())
      ->setPreviewPanel(
        id(new PHUIRemarkupPreviewPanel())
          ->setHeader(pht('Description Preview')));

    return $fields;
  }

  private function getTaskStatusMap(ManiphestTask $task) {
    $status_map = ManiphestTaskStatus::getTaskStatusMap();

    $current_status = $task->getStatus();

    // If the current status is something we don't recognize (maybe an older
    // status which was deleted), put a dummy entry in the status map so that
    // saving the form doesn't destroy any data by accident.
    if (idx($status_map, $current_status) === null) {
      $status_map[$current_status] = pht('<Unknown: %s>', $current_status);
    }

    $dup_status = ManiphestTaskStatus::getDuplicateStatus();
    foreach ($status_map as $status => $status_name) {
      // Always keep the task's current status.
      if ($status == $current_status) {
        continue;
      }

      // Don't allow tasks to be changed directly into "Closed, Duplicate"
      // status. Instead, you have to merge them. See T4819.
      if ($status == $dup_status) {
        unset($status_map[$status]);
        continue;
      }

      // Don't let new or existing tasks be moved into a disabled status.
      if (ManiphestTaskStatus::isDisabledStatus($status)) {
        unset($status_map[$status]);
        continue;
      }
    }

    return $status_map;
  }

  private function getTaskPriorityMap(ManiphestTask $task) {
    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
    $current_priority = $task->getPriority();

    // If the current value isn't a legitimate one, put it in the dropdown
    // anyway so saving the form doesn't cause a side effects.
    if (idx($priority_map, $current_priority) === null) {
      $priority_map[$current_priority] = pht(
        '<Unknown: %s>',
        $current_priority);
    }

    foreach ($priority_map as $priority => $priority_name) {
      // Always keep the current priority.
      if ($priority == $current_priority) {
        continue;
      }

      if (ManiphestTaskPriority::isDisabledPriority($priority)) {
        unset($priority_map[$priority]);
        continue;
      }
    }

    return $priority_map;
  }

  protected function newEditResponse(
    AphrontRequest $request,
    $object,
    array $xactions) {

    if ($request->isAjax()) {
      // Reload the task to make sure we pick up the final task state.
      $viewer = $this->getViewer();
      $task = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withIDs(array($object->getID()))
        ->needSubscriberPHIDs(true)
        ->needProjectPHIDs(true)
        ->executeOne();

      switch ($request->getStr('responseType')) {
        case 'card':
          return $this->buildCardResponse($task);
        default:
          return $this->buildListResponse($task);
      }

    }

    return parent::newEditResponse($request, $object, $xactions);
  }

  private function buildListResponse(ManiphestTask $task) {
    $controller = $this->getController();

    $payload = array(
      'tasks' => $controller->renderSingleTask($task),
      'data' => array(),
    );

    return id(new AphrontAjaxResponse())->setContent($payload);
  }

  private function buildCardResponse(ManiphestTask $task) {
    $controller = $this->getController();
    $request = $controller->getRequest();
    $viewer = $request->getViewer();

    $column_phid = $request->getStr('columnPHID');

    $visible_phids = $request->getStrList('visiblePHIDs');
    if (!$visible_phids) {
      $visible_phids = array();
    }

    $column = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($column_phid))
      ->executeOne();
    if (!$column) {
      return new Aphront404Response();
    }

    $board_phid = $column->getProjectPHID();
    $object_phid = $task->getPHID();

    return id(new PhabricatorBoardResponseEngine())
      ->setViewer($viewer)
      ->setBoardPHID($board_phid)
      ->setObjectPHID($object_phid)
      ->setVisiblePHIDs($visible_phids)
      ->buildResponse();
  }


}
