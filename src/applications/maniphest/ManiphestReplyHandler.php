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
      return "Reply to comment or attach files, or !close, !claim, or ".
             "!unsubscribe.";
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

    $body = $mail->getCleanTextBody();
    $body = trim($body);
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $xactions = array();
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $template = new ManiphestTransaction();
    $template->setContentSource($content_source);
    $template->setAuthorPHID($user->getPHID());


    if ($is_new_task) {
      // If this is a new task, create a "User created this task." transaction
      // and then set the title and description.
      $xaction = clone $template;
      $xaction->setTransactionType(ManiphestTransactionType::TYPE_STATUS);
      $xaction->setNewValue(ManiphestTaskStatus::STATUS_OPEN);
      $xactions[] = $xaction;

      $task->setAuthorPHID($user->getPHID());
      $task->setTitle(nonempty($mail->getSubject(), 'Untitled Task'));
      $task->setDescription($body);
      $task->setPriority(ManiphestTaskPriority::getDefaultPriority());

    } else {
      $lines = explode("\n", trim($body));
      $first_line = head($lines);

      $command = null;
      $matches = null;
      if (preg_match('/^!(\w+)/', $first_line, $matches)) {
        $lines = array_slice($lines, 1);
        $body = implode("\n", $lines);
        $body = trim($body);

        $command = $matches[1];
      }

      $ttype = ManiphestTransactionType::TYPE_NONE;
      $new_value = null;
      switch ($command) {
        case 'close':
          $ttype = ManiphestTransactionType::TYPE_STATUS;
          $new_value = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
          break;
        case 'claim':
          $ttype = ManiphestTransactionType::TYPE_OWNER;
          $new_value = $user->getPHID();
          break;
        case 'unsubscribe':
          $ttype = ManiphestTransactionType::TYPE_CCS;
          $ccs = $task->getCCPHIDs();
          foreach ($ccs as $k => $phid) {
            if ($phid == $user->getPHID()) {
              unset($ccs[$k]);
            }
          }
          $new_value = array_values($ccs);
          break;
      }

      $xaction = clone $template;
      $xaction->setTransactionType($ttype);
      $xaction->setNewValue($new_value);
      $xaction->setComments($body);

      $xactions[] = $xaction;
    }

    $ccs = $mail->loadCCPHIDs();
    if ($ccs) {
      $old_ccs = $task->getCCPHIDs();
      $new_ccs = array_unique(array_merge($old_ccs, $ccs));
      $cc_xaction = clone $template;
      $cc_xaction->setTransactionType(ManiphestTransactionType::TYPE_CCS);
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


    $editor = new ManiphestTransactionEditor();
    $editor->setActor($user);
    $editor->setParentMessageID($mail->getMessageID());
    $editor->setExcludeMailRecipientPHIDs(
      $this->getExcludeMailRecipientPHIDs());
    $editor->applyTransactions($task, $xactions);

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
