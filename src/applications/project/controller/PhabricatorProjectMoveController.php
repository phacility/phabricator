<?php

final class PhabricatorProjectMoveController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

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
          'columnPHIDs' => $old_column_phids,
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
        ->needProjectPHIDs(true)
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

      $try = array(
        array($after_phid, true),
        array($before_phid, false),
      );

      $pri = null;
      $sub = null;
      foreach ($try as $spec) {
        list($task_phid, $is_after) = $spec;
        $task = idx($tasks, $task_phid);
        if ($task) {
          list($pri, $sub) = ManiphestTransactionEditor::getAdjacentSubpriority(
            $task,
            $is_after);
          break;
        }
      }

      if ($pri !== null) {
        $xactions[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransaction::TYPE_PRIORITY)
          ->setNewValue($pri);
        $xactions[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
          ->setNewValue($sub);
      }
    }

    $proxy = $column->getProxy();
    if ($proxy) {
      // We're moving the task into a subproject or milestone column, so add
      // the subproject or milestone.
      $add_projects = array($proxy->getPHID());
    } else if ($project->getHasSubprojects() || $project->getHasMilestones()) {
      // We're moving the task into the "Backlog" column on the parent project,
      // so add the parent explicitly. This gets rid of any subproject or
      // milestone tags.
      $add_projects = array($project->getPHID());
    } else {
      $add_projects = array();
    }

    if ($add_projects) {
      $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $project_type)
        ->setNewValue(
          array(
            '+' => array_fuse($add_projects),
          ));
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

    // Reload the object so it reflects edits which have been applied.
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

    $except_phids = array($board_phid);
    if ($project->getHasSubprojects() || $project->getHasMilestones()) {
      $descendants = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withAncestorProjectPHIDs($except_phids)
        ->execute();
      foreach ($descendants as $descendant) {
        $except_phids[] = $descendant->getPHID();
      }
    }

    $except_phids = array_fuse($except_phids);
    $handle_phids = array_fuse($object->getProjectPHIDs());
    $handle_phids = array_diff_key($handle_phids, $except_phids);

    $project_handles = $viewer->loadHandles($handle_phids);
    $project_handles = iterator_to_array($project_handles);

    $card = id(new ProjectBoardTaskCard())
      ->setViewer($viewer)
      ->setTask($object)
      ->setOwner($owner)
      ->setCanEdit(true)
      ->setProjectHandles($project_handles)
      ->getItem();

    $card->addClass('phui-workcard');

    return id(new AphrontAjaxResponse())->setContent(
      array('task' => $card));
  }

}
