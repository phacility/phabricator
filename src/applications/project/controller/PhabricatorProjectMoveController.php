<?php

final class PhabricatorProjectMoveController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $request->validateCSRF();

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
      ->withIDs(array($id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $board_phid = $project->getPHID();

    $object = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->needProjectPHIDs(true)
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

    $engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs(array($object_phid))
      ->executeLayout();

    $columns = $engine->getObjectColumns($board_phid, $object_phid);
    $old_column_phids = mpull($columns, 'getPHID');

    $xactions = array();

    $order_params = array();
    if ($order == PhabricatorProjectColumn::ORDER_NATURAL) {
      if ($after_phid) {
        $order_params['afterPHID'] = $after_phid;
      } else if ($before_phid) {
        $order_params['beforePHID'] = $before_phid;
      }
    }

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COLUMNS)
      ->setNewValue(
        array(
          array(
            'columnPHID' => $column->getPHID(),
          ) + $order_params,
        ));

    if ($order == PhabricatorProjectColumn::ORDER_PRIORITY) {
      $priority_xactions = $this->getPriorityTransactions(
        $object,
        $after_phid,
        $before_phid);
      foreach ($priority_xactions as $xaction) {
        $xactions[] = $xaction;
      }
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($object, $xactions);

    return $this->newCardResponse($board_phid, $object_phid);
  }

  private function getPriorityTransactions(
    ManiphestTask $task,
    $after_phid,
    $before_phid) {

    list($after_task, $before_task) = $this->loadPriorityTasks(
      $after_phid,
      $before_phid);

    $must_move = false;
    if ($after_task && !$task->isLowerPriorityThan($after_task)) {
      $must_move = true;
    }

    if ($before_task && !$task->isHigherPriorityThan($before_task)) {
      $must_move = true;
    }

    // The move doesn't require a priority change to be valid, so don't
    // change the priority since we are not being forced to.
    if (!$must_move) {
      return array();
    }

    $try = array(
      array($after_task, true),
      array($before_task, false),
    );

    $pri = null;
    $sub = null;
    foreach ($try as $spec) {
      list($task, $is_after) = $spec;

      if (!$task) {
        continue;
      }

      list($pri, $sub) = ManiphestTransactionEditor::getAdjacentSubpriority(
        $task,
        $is_after);

      // If we find a priority on the first try, don't keep going.
      break;
    }

    $keyword_map = ManiphestTaskPriority::getTaskPriorityKeywordsMap();
    $keyword = head(idx($keyword_map, $pri));

    $xactions = array();
    if ($pri !== null) {
      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(ManiphestTaskPriorityTransaction::TRANSACTIONTYPE)
        ->setNewValue($keyword);
      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(
          ManiphestTaskSubpriorityTransaction::TRANSACTIONTYPE)
        ->setNewValue($sub);
    }

    return $xactions;
  }

  private function loadPriorityTasks($after_phid, $before_phid) {
    $viewer = $this->getViewer();

    $task_phids = array();

    if ($after_phid) {
      $task_phids[] = $after_phid;
    }
    if ($before_phid) {
      $task_phids[] = $before_phid;
    }

    if (!$task_phids) {
      return array(null, null);
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs($task_phids)
      ->execute();
    $tasks = mpull($tasks, null, 'getPHID');

    if ($after_phid) {
      $after_task = idx($tasks, $after_phid);
    } else {
      $after_task = null;
    }

    if ($before_phid) {
      $before_task = idx($tasks, $before_phid);
    } else {
      $before_task = null;
    }

    return array($after_task, $before_task);
  }

}
