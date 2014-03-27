<?php

/**
 * @group maniphest
 */
final class ManiphestReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ManiphestTask)) {
      throw new Exception("Mail receiver is not a ManiphestTask!");
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'T');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('T');
  }

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.reply-handler-domain');
  }

  public function getReplyHandlerInstructions() {
    if ($this->supportsReplies()) {
      return "Reply to comment or attach files, or !close, !claim, ".
             "!unsubscribe or !assign <username>.";
    } else {
      return null;
    }
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {

    // NOTE: We'll drop in here on both the "reply to a task" and "create a
    // new task" workflows! Make sure you test both if you make changes!

    $task = $this->getMailReceiver();

    $is_new_task = !$task->getID();

    $user = $this->getActor();

    $body_data = $mail->parseBody();
    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $xactions = array();
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $template = new ManiphestTransaction();

    $is_unsub = false;
    if ($is_new_task) {
      // If this is a new task, create a "User created this task." transaction
      // and then set the title and description.
      $xaction = clone $template;
      $xaction->setTransactionType(ManiphestTransaction::TYPE_STATUS);
      $xaction->setNewValue(ManiphestTaskStatus::getDefaultStatus());
      $xactions[] = $xaction;

      $task->setAuthorPHID($user->getPHID());
      $task->setTitle(nonempty($mail->getSubject(), 'Untitled Task'));
      $task->setDescription($body);
      $task->setPriority(ManiphestTaskPriority::getDefaultPriority());

    } else {

      $command = $body_data['command'];
      $command_value = $body_data['command_value'];

      $ttype = PhabricatorTransactions::TYPE_COMMENT;
      $new_value = null;
      switch ($command) {
        case 'close':
          $ttype = ManiphestTransaction::TYPE_STATUS;
          $new_value = ManiphestTaskStatus::getDefaultClosedStatus();
          break;
        case 'claim':
          $ttype = ManiphestTransaction::TYPE_OWNER;
          $new_value = $user->getPHID();
          break;
        case 'assign':
          $ttype = ManiphestTransaction::TYPE_OWNER;
          if ($command_value) {
            $assign_users = id(new PhabricatorPeopleQuery())
              ->setViewer($user)
              ->withUsernames(array($command_value))
              ->execute();
            if ($assign_users) {
              $assign_user = head($assign_users);
              $new_value = $assign_user->getPHID();
            }
          }
          // assign to the user by default
          if (!$new_value) {
            $new_value = $user->getPHID();
          }
          break;
        case 'unsubscribe':
          $is_unsub = true;
          $ttype = ManiphestTransaction::TYPE_CCS;
          $ccs = $task->getCCPHIDs();
          foreach ($ccs as $k => $phid) {
            if ($phid == $user->getPHID()) {
              unset($ccs[$k]);
            }
          }
          $new_value = array_values($ccs);
          break;
      }

      if ($ttype != PhabricatorTransactions::TYPE_COMMENT) {
        $xaction = clone $template;
        $xaction->setTransactionType($ttype);
        $xaction->setNewValue($new_value);
        $xactions[] = $xaction;
      }

      if (strlen($body)) {
        $xaction = clone $template;
        $xaction->setTransactionType(PhabricatorTransactions::TYPE_COMMENT);
        $xaction->attachComment(
          id(new ManiphestTransactionComment())
            ->setContent($body));
        $xactions[] = $xaction;
      }

    }

    $ccs = $mail->loadCCPHIDs();
    $old_ccs = $task->getCCPHIDs();
    $new_ccs = array_merge($old_ccs, $ccs);
    if (!$is_unsub) {
      $new_ccs[] = $user->getPHID();
    }
    $new_ccs = array_unique($new_ccs);

    if (array_diff($new_ccs, $old_ccs)) {
      $cc_xaction = clone $template;
      $cc_xaction->setTransactionType(ManiphestTransaction::TYPE_CCS);
      $cc_xaction->setNewValue($new_ccs);
      $xactions[] = $cc_xaction;
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
      array(
        'task'          => $task,
        'mail'          => $mail,
        'new'           => $is_new_task,
        'transactions'  => $xactions,
      ));
    $event->setUser($user);
    PhutilEventEngine::dispatchEvent($event);

    $task = $event->getValue('task');
    $xactions = $event->getValue('transactions');

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($user)
      ->setParentMessageID($mail->getMessageID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->setContentSource($content_source)
      ->applyTransactions($task, $xactions);

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_DIDEDITTASK,
      array(
        'task'          => $task,
        'new'           => $is_new_task,
        'transactions'  => $xactions,
      ));
    $event->setUser($user);
    PhutilEventEngine::dispatchEvent($event);

  }

}
