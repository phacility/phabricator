<?php

final class ManiphestTransactionSaveController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getStr('taskID')))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $task_uri = '/'.$task->getMonogram();

    $transactions = array();

    $action = $request->getStr('action');

    $implicit_ccs = array();
    $explicit_ccs = array();

    $transaction = new ManiphestTransaction();
    $transaction
      ->setTransactionType($action);

    switch ($action) {
      case ManiphestTransaction::TYPE_STATUS:
        $transaction->setNewValue($request->getStr('resolution'));
        break;
      case ManiphestTransaction::TYPE_OWNER:
        $assign_to = $request->getArr('assign_to');
        $assign_to = reset($assign_to);
        $transaction->setNewValue($assign_to);
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        $projects = $request->getArr('projects');
        $projects = array_merge($projects, $task->getProjectPHIDs());
        $projects = array_filter($projects);
        $projects = array_unique($projects);

        $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $transaction
          ->setMetadataValue('edge:type', $project_type)
          ->setNewValue(
            array(
              '+' => array_fuse($projects),
            ));
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        // Accumulate the new explicit CCs into the array that we'll add in
        // the CC transaction later.
        $explicit_ccs = $request->getArr('ccs');

        // Throw away the primary transaction.
        $transaction = null;
        break;
      case ManiphestTransaction::TYPE_PRIORITY:
        $transaction->setNewValue($request->getInt('priority'));
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        // Nuke this, we're going to create it below.
        $transaction = null;
        break;
      default:
        throw new Exception(pht("Unknown action '%s'!", $action));
    }

    if ($transaction) {
      $transactions[] = $transaction;
    }


    // When you interact with a task, we add you to the CC list so you get
    // further updates, and possibly assign the task to you if you took an
    // ownership action (closing it) but it's currently unowned. We also move
    // previous owners to CC if ownership changes. Detect all these conditions
    // and create side-effect transactions for them.

    $implicitly_claimed = false;
    if ($action == ManiphestTransaction::TYPE_OWNER) {
      if ($task->getOwnerPHID() == $transaction->getNewValue()) {
        // If this is actually no-op, don't generate the side effect.
      } else {
        // Otherwise, when a task is reassigned, move the previous owner to CC.
        if ($task->getOwnerPHID()) {
          $implicit_ccs[] = $task->getOwnerPHID();
        }
      }
    }

    if ($action == ManiphestTransaction::TYPE_STATUS) {
      $resolution = $request->getStr('resolution');
      if (!$task->getOwnerPHID() &&
          ManiphestTaskStatus::isClosedStatus($resolution)) {
        // Closing an unassigned task. Assign the user as the owner of
        // this task.
        $assign = new ManiphestTransaction();
        $assign->setTransactionType(ManiphestTransaction::TYPE_OWNER);
        $assign->setNewValue($viewer->getPHID());
        $transactions[] = $assign;

        $implicitly_claimed = true;
      }
    }

    $user_owns_task = false;
    if ($implicitly_claimed) {
      $user_owns_task = true;
    } else {
      if ($action == ManiphestTransaction::TYPE_OWNER) {
        if ($transaction->getNewValue() == $viewer->getPHID()) {
          $user_owns_task = true;
        }
      } else if ($task->getOwnerPHID() == $viewer->getPHID()) {
        $user_owns_task = true;
      }
    }

    if (!$user_owns_task) {
      // If we aren't making the user the new task owner and they aren't the
      // existing task owner, add them to CC unless they're aleady CC'd.
      if (!in_array($viewer->getPHID(), $task->getSubscriberPHIDs())) {
        $implicit_ccs[] = $viewer->getPHID();
      }
    }

    if ($implicit_ccs || $explicit_ccs) {

      // TODO: These implicit CC rules should probably be handled inside the
      // Editor, eventually.

      $all_ccs = array_fuse($implicit_ccs) + array_fuse($explicit_ccs);

      $cc_transaction = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue(array('+' => $all_ccs));

      if (!$explicit_ccs) {
        $cc_transaction->setIgnoreOnNoEffect(true);
      }

      $transactions[] = $cc_transaction;
    }

    $comments = $request->getStr('comments');
    if (strlen($comments) || !$transactions) {
      $transactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new ManiphestTransactionComment())
            ->setContent($comments));
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
      array(
        'task'          => $task,
        'new'           => false,
        'transactions'  => $transactions,
      ));
    $event->setUser($viewer);
    $event->setAphrontRequest($request);
    PhutilEventEngine::dispatchEvent($event);

    $task = $event->getValue('task');
    $transactions = $event->getValue('transactions');

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect($request->isContinueRequest());

    try {
      $editor->applyTransactions($task, $transactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($task_uri)
        ->setException($ex);
    }

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $viewer->getPHID(),
      $task->getPHID());
    if ($draft) {
      $draft->delete();
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_DIDEDITTASK,
      array(
        'task'          => $task,
        'new'           => false,
        'transactions'  => $transactions,
      ));
    $event->setUser($viewer);
    $event->setAphrontRequest($request);
    PhutilEventEngine::dispatchEvent($event);

    return id(new AphrontRedirectResponse())->setURI($task_uri);
  }

}
