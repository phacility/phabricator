<?php

final class ManiphestTransactionSaveController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withIDs(array($request->getStr('taskID')))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $task_uri = '/'.$task->getMonogram();

    $transactions = array();

    $action = $request->getStr('action');

    // If we have drag-and-dropped files, attach them first in a separate
    // transaction. These can come in on any transaction type, which is why we
    // handle them separately.
    $files = array();

    // Look for drag-and-drop uploads first.
    $file_phids = $request->getArr('files');
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($user)
        ->withPHIDs(array($file_phids))
        ->execute();
    }

    // This means "attach a file" even though we store other types of data
    // as 'attached'.
    if ($action == ManiphestTransaction::TYPE_ATTACH) {
      if (!empty($_FILES['file'])) {
        $err = idx($_FILES['file'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['file'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $files[] = $file;
        }
      }
    }

    // If we had explicit or drag-and-drop files, create a transaction
    // for those before we deal with whatever else might have happened.
    $file_transaction = null;
    if ($files) {
      $files = mpull($files, 'getPHID', 'getPHID');
      $new = $task->getAttached();
      foreach ($files as $phid) {
        if (empty($new[PhabricatorFilePHIDTypeFile::TYPECONST])) {
          $new[PhabricatorFilePHIDTypeFile::TYPECONST] = array();
        }
        $new[PhabricatorFilePHIDTypeFile::TYPECONST][$phid] = array();
      }
      $transaction = new ManiphestTransaction();
      $transaction
        ->setTransactionType(ManiphestTransaction::TYPE_ATTACH);
      $transaction->setNewValue($new);
      $transactions[] = $transaction;
    }

    // Compute new CCs added by @mentions. Several things can cause CCs to
    // be added as side effects: mentions, explicit CCs, users who aren't
    // CC'd interacting with the task, and ownership changes. We build up a
    // list of all the CCs and then construct a transaction for them at the
    // end if necessary.
    $added_ccs = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      array(
        $request->getStr('comments'),
      ));

    $cc_transaction = new ManiphestTransaction();
    $cc_transaction
      ->setTransactionType(ManiphestTransaction::TYPE_CCS);

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
      case ManiphestTransaction::TYPE_PROJECTS:
        $projects = $request->getArr('projects');
        $projects = array_merge($projects, $task->getProjectPHIDs());
        $projects = array_filter($projects);
        $projects = array_unique($projects);
        $transaction->setNewValue($projects);
        break;
      case ManiphestTransaction::TYPE_CCS:
        // Accumulate the new explicit CCs into the array that we'll add in
        // the CC transaction later.
        $added_ccs = array_merge($added_ccs, $request->getArr('ccs'));

        // Throw away the primary transaction.
        $transaction = null;
        break;
      case ManiphestTransaction::TYPE_PRIORITY:
        $transaction->setNewValue($request->getInt('priority'));
        break;
      case ManiphestTransaction::TYPE_ATTACH:
        // Nuke this, we created it above.
        $transaction = null;
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        // Nuke this, we're going to create it below.
        $transaction = null;
        break;
      default:
        throw new Exception('unknown action');
    }

    if ($transaction) {
      $transactions[] = $transaction;
    }

    $resolution = $request->getStr('resolution');
    $did_scuttle = false;
    if ($action !== ManiphestTransaction::TYPE_STATUS) {
      if ($request->getStr('scuttle')) {
        $transactions[] = id(new ManiphestTransaction())
          ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
          ->setNewValue(ManiphestTaskStatus::getDefaultClosedStatus());
        $did_scuttle = true;
        $resolution = ManiphestTaskStatus::getDefaultClosedStatus();
      }
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
        break;
      }
      // Otherwise, when a task is reassigned, move the previous owner to CC.
      $added_ccs[] = $task->getOwnerPHID();
    }

    if ($did_scuttle || ($action == ManiphestTransaction::TYPE_STATUS)) {
      if (!$task->getOwnerPHID() &&
          ManiphestTaskStatus::isClosedStatus($resolution)) {
        // Closing an unassigned task. Assign the user as the owner of
        // this task.
        $assign = new ManiphestTransaction();
        $assign->setTransactionType(ManiphestTransaction::TYPE_OWNER);
        $assign->setNewValue($user->getPHID());
        $transactions[] = $assign;

        $implicitly_claimed = true;
      }
    }

    $user_owns_task = false;
    if ($implicitly_claimed) {
      $user_owns_task = true;
    } else {
      if ($action == ManiphestTransaction::TYPE_OWNER) {
        if ($transaction->getNewValue() == $user->getPHID()) {
          $user_owns_task = true;
        }
      } else if ($task->getOwnerPHID() == $user->getPHID()) {
        $user_owns_task = true;
      }
    }

    if (!$user_owns_task) {
      // If we aren't making the user the new task owner and they aren't the
      // existing task owner, add them to CC unless they're aleady CC'd.
      if (!in_array($user->getPHID(), $task->getCCPHIDs())) {
        $added_ccs[] = $user->getPHID();
      }
    }

    // Evade no-effect detection in the new editor stuff until we can switch
    // to subscriptions.
    $added_ccs = array_filter(array_diff($added_ccs, $task->getCCPHIDs()));

    if ($added_ccs) {
      // We've added CCs, so include a CC transaction.
      $all_ccs = array_merge($task->getCCPHIDs(), $added_ccs);
      $cc_transaction->setNewValue($all_ccs);
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
    $event->setUser($user);
    $event->setAphrontRequest($request);
    PhutilEventEngine::dispatchEvent($event);

    $task = $event->getValue('task');
    $transactions = $event->getValue('transactions');

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($user)
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
      $user->getPHID(),
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
    $event->setUser($user);
    $event->setAphrontRequest($request);
    PhutilEventEngine::dispatchEvent($event);

    return id(new AphrontRedirectResponse())->setURI($task_uri);
  }

}
