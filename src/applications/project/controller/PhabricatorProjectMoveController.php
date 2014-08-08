<?php

final class PhabricatorProjectMoveController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $column_phid = $request->getStr('columnPHID');
    $object_phid = $request->getStr('objectPHID');
    $after_phid = $request->getStr('afterPHID');
    $before_phid = $request->getStr('beforePHID');
    $order = $request->getStr('order', PhabricatorProjectColumn::DEFAULT_ORDER);


    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$object) {
      return new Aphront404Response();
    }

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();

    $columns = mpull($columns, null, 'getPHID');
    $column = idx($columns, $column_phid);
    if (!$column) {
      // User is trying to drop this object into a nonexistent column, just kick
      // them out.
      return new Aphront404Response();
    }

    $positions = id(new PhabricatorProjectColumnPositionQuery())
      ->setViewer($viewer)
      ->withColumns($columns)
      ->withObjectPHIDs(array($object_phid))
      ->execute();

    $xactions = array();

    if ($order == PhabricatorProjectColumn::ORDER_NATURAL) {
      $order_params = array(
        'afterPHID' => $after_phid,
        'beforePHID' => $before_phid,
      );
    } else {
      $order_params = array();
    }

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_PROJECT_COLUMN)
      ->setNewValue(
        array(
          'columnPHIDs' => array($column->getPHID()),
          'projectPHID' => $column->getProjectPHID(),
        ) + $order_params)
      ->setOldValue(
        array(
          'columnPHIDs' => mpull($positions, 'getColumnPHID'),
          'projectPHID' => $column->getProjectPHID(),
        ));

    $task_phids = array();
    if ($after_phid) {
      $task_phids[] = $after_phid;
    }
    if ($before_phid) {
      $task_phids[] = $before_phid;
    }

    if ($task_phids && ($order == PhabricatorProjectColumn::ORDER_PRIORITY)) {
      $tasks = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withPHIDs($task_phids)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->execute();
      if (count($tasks) != count($task_phids)) {
        return new Aphront404Response();
      }
      $tasks = mpull($tasks, null, 'getPHID');

      $a_task = idx($tasks, $after_phid);
      $b_task = idx($tasks, $before_phid);

      if ($a_task &&
         (($a_task->getPriority() < $object->getPriority()) ||
          ($a_task->getPriority() == $object->getPriority() &&
           $a_task->getSubpriority() >= $object->getSubpriority()))) {

        $after_pri = $a_task->getPriority();
        $after_sub = $a_task->getSubpriority();

        $xactions[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
          ->setNewValue(array(
            'newPriority' => $after_pri,
            'newSubpriorityBase' => $after_sub,
            'direction' => '>'));

       } else if ($b_task &&
                 (($b_task->getPriority() > $object->getPriority()) ||
                  ($b_task->getPriority() == $object->getPriority() &&
                   $b_task->getSubpriority() <= $object->getSubpriority()))) {

        $before_pri = $b_task->getPriority();
        $before_sub = $b_task->getSubpriority();

        $xactions[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
          ->setNewValue(array(
            'newPriority' => $before_pri,
            'newSubpriorityBase' => $before_sub,
            'direction' => '<'));
      }
   }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($object, $xactions);

    $owner = null;
    if ($object->getOwnerPHID()) {
      $owner = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($object->getOwnerPHID()))
        ->executeOne();
    }
    $card = id(new ProjectBoardTaskCard())
      ->setViewer($viewer)
      ->setTask($object)
      ->setOwner($owner)
      ->setCanEdit(true)
      ->getItem();

    return id(new AphrontAjaxResponse())->setContent(
      array('task' => $card));
 }

}
