<?php

/**
 * @group maniphest
 */
final class ManiphestTransactionSaveController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new ManiphestTask())->load($request->getStr('taskID'));
    if (!$task) {
      return new Aphront404Response();
    }

    $transactions = array();

    $action = $request->getStr('action');

    // If we have drag-and-dropped files, attach them first in a separate
    // transaction. These can come in on any transaction type, which is why we
    // handle them separately.
    $files = array();

    // Look for drag-and-drop uploads first.
    $file_phids = $request->getArr('files');
    if ($file_phids) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid in (%Ls)',
        $file_phids);
    }

    // This means "attach a file" even though we store other types of data
    // as 'attached'.
    if ($action == ManiphestTransactionType::TYPE_ATTACH) {
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
        if (empty($new[PhabricatorPHIDConstants::PHID_TYPE_FILE])) {
          $new[PhabricatorPHIDConstants::PHID_TYPE_FILE] = array();
        }
        $new[PhabricatorPHIDConstants::PHID_TYPE_FILE][$phid] = array();
      }
      $transaction = new ManiphestTransaction();
      $transaction
        ->setAuthorPHID($user->getPHID())
        ->setTransactionType(ManiphestTransactionType::TYPE_ATTACH);
      $transaction->setNewValue($new);
      $transactions[] = $transaction;
      $file_transaction = $transaction;
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
      ->setAuthorPHID($user->getPHID())
      ->setTransactionType(ManiphestTransactionType::TYPE_CCS);
    $force_cc_transaction = false;

    $transaction = new ManiphestTransaction();
    $transaction
      ->setAuthorPHID($user->getPHID())
      ->setComments($request->getStr('comments'))
      ->setTransactionType($action);

    switch ($action) {
      case ManiphestTransactionType::TYPE_STATUS:
        $transaction->setNewValue($request->getStr('resolution'));
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        $assign_to = $request->getArr('assign_to');
        $assign_to = reset($assign_to);
        $transaction->setNewValue($assign_to);
        break;
      case ManiphestTransactionType::TYPE_PROJECTS:
        $projects = $request->getArr('projects');
        $projects = array_merge($projects, $task->getProjectPHIDs());
        $projects = array_filter($projects);
        $projects = array_unique($projects);
        $transaction->setNewValue($projects);
        break;
      case ManiphestTransactionType::TYPE_CCS:
        // Accumulate the new explicit CCs into the array that we'll add in
        // the CC transaction later.
        $added_ccs = array_merge($added_ccs, $request->getArr('ccs'));

        // Transfer any comments over to the CC transaction.
        $cc_transaction->setComments($transaction->getComments());

        // Make sure we include this transaction, even if the user didn't
        // actually add any CC's, because we'll discard their comment otherwise.
        $force_cc_transaction = true;

        // Throw away the primary transaction.
        $transaction = null;
        break;
      case ManiphestTransactionType::TYPE_PRIORITY:
        $transaction->setNewValue($request->getInt('priority'));
        break;
      case ManiphestTransactionType::TYPE_NONE:
      case ManiphestTransactionType::TYPE_ATTACH:
        // If we have a file transaction, just get rid of this secondary
        // transaction and put the comments on it instead.
        if ($file_transaction) {
          $file_transaction->setComments($transaction->getComments());
          $transaction = null;
        }
        break;
      default:
        throw new Exception('unknown action');
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
    switch ($action) {
      case ManiphestTransactionType::TYPE_OWNER:
        if ($task->getOwnerPHID() == $transaction->getNewValue()) {
          // If this is actually no-op, don't generate the side effect.
          break;
        }
        // Otherwise, when a task is reassigned, move the previous owner to CC.
        $added_ccs[] = $task->getOwnerPHID();
        break;
      case ManiphestTransactionType::TYPE_STATUS:
        if (!$task->getOwnerPHID() &&
            $request->getStr('resolution') !=
            ManiphestTaskStatus::STATUS_OPEN) {
          // Closing an unassigned task. Assign the user as the owner of
          // this task.
          $assign = new ManiphestTransaction();
          $assign->setAuthorPHID($user->getPHID());
          $assign->setTransactionType(ManiphestTransactionType::TYPE_OWNER);
          $assign->setNewValue($user->getPHID());
          $transactions[] = $assign;

          $implicitly_claimed = true;
        }
        break;
    }


    $user_owns_task = false;
    if ($implicitly_claimed) {
      $user_owns_task = true;
    } else {
      if ($action == ManiphestTransactionType::TYPE_OWNER) {
        if ($transaction->getNewValue() == $user->getPHID()) {
          $user_owns_task = true;
        }
      } else if ($task->getOwnerPHID() == $user->getPHID()) {
        $user_owns_task = true;
      }
    }

    if (!$user_owns_task) {
      // If we aren't making the user the new task owner and they aren't the
      // existing task owner, add them to CC.
      $added_ccs[] = $user->getPHID();
    }

    if ($added_ccs || $force_cc_transaction) {
      // We've added CCs, so include a CC transaction. It's safe to do this even
      // if we're just "adding" CCs which already exist, because the
      // ManiphestTransactionEditor is smart enough to ignore them.
      $all_ccs = array_merge($task->getCCPHIDs(), $added_ccs);
      $cc_transaction->setNewValue($all_ccs);
      $transactions[] = $cc_transaction;
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    foreach ($transactions as $transaction) {
      $transaction->setContentSource($content_source);
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

    $editor = new ManiphestTransactionEditor();
    $editor->setActor($user);
    $editor->applyTransactions($task, $transactions);

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

    return id(new AphrontRedirectResponse())
      ->setURI('/T'.$task->getID());
  }

}
