<?php

final class ManiphestReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ManiphestTask)) {
      throw new Exception('Mail receiver is not a ManiphestTask!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'T');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('T');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // NOTE: We'll drop in here on both the "reply to a task" and "create a
    // new task" workflows! Make sure you test both if you make changes!
    $task = $this->getMailReceiver();
    $viewer = $this->getActor();

    $is_new_task = !$task->getID();

    $body_data = $mail->parseBody();
    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $template = new ManiphestTransaction();

    $xactions = array();
    if ($is_new_task) {
      $xactions[] = id(clone $template)
        ->setTransactionType(ManiphestTransaction::TYPE_TITLE)
        ->setNewValue(nonempty($mail->getSubject(), pht('Untitled Task')));

      $xactions[] = id(clone $template)
        ->setTransactionType(ManiphestTransaction::TYPE_DESCRIPTION)
        ->setNewValue($body);
    } else {
      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new ManiphestTransactionComment())
            ->setContent($body));
    }

    $commands = $body_data['commands'];
    foreach ($commands as $command_argv) {
      $command = head($command_argv);
      $args = array_slice($command_argv, 1);
      switch ($command) {
        case 'close':
          $xactions[] = id(clone $template)
            ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
            ->setNewValue(ManiphestTaskStatus::getDefaultClosedStatus());
          break;
        case 'claim':
          $xactions[] = id(clone $template)
            ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
            ->setNewValue($viewer->getPHID());
          break;
        case 'assign':
          $assign_to = head($args);
          if ($assign_to) {
            $assign_user = id(new PhabricatorPeopleQuery())
              ->setViewer($viewer)
              ->withUsernames(array($assign_to))
              ->executeOne();
            if ($assign_user) {
              $assign_phid = $assign_user->getPHID();
            }
          }

          // Treat bad "!assign" like "!claim".
          if (!$assign_phid) {
            $assign_phid = $viewer->getPHID();
          }

          $xactions[] = id(clone $template)
            ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
            ->setNewValue($assign_phid);
          break;
        case 'unsubscribe':
          $xactions[] = id(clone $template)
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(
              array(
                '-' => array($viewer->getPHID()),
              ));
          break;
      }
    }

    $ccs = $mail->loadCCPHIDs();
    if ($ccs) {
      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue(
          array(
            '+' => array($viewer->getPHID()),
          ));
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
      array(
        'task'          => $task,
        'mail'          => $mail,
        'new'           => $is_new_task,
        'transactions'  => $xactions,
      ));
    $event->setUser($viewer);
    PhutilEventEngine::dispatchEvent($event);

    $task = $event->getValue('task');
    $xactions = $event->getValue('transactions');

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setParentMessageID($mail->getMessageID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->setContentSource($content_source);

    if ($this->getApplicationEmail()) {
      $editor->setApplicationEmail($this->getApplicationEmail());
    }

    $editor->applyTransactions($task, $xactions);

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_DIDEDITTASK,
      array(
        'task'          => $task,
        'new'           => $is_new_task,
        'transactions'  => $xactions,
      ));
    $event->setUser($viewer);
    PhutilEventEngine::dispatchEvent($event);
  }

}
