<?php

abstract class PhabricatorApplicationTransactionReplyHandler
  extends PhabricatorMailReplyHandler {

  abstract public function getObjectPrefix();

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress(
      $handle,
      $this->getObjectPrefix());
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress(
      $this->getObjectPrefix());
  }

  private function newEditor(PhabricatorMetaMTAReceivedMail $mail) {
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $editor = $this->getMailReceiver()
      ->getApplicationTransactionEditor()
      ->setActor($this->getActor())
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true)
      ->setParentMessageID($mail->getMessageID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs());

    if ($this->getApplicationEmail()) {
      $editor->setApplicationEmail($this->getApplicationEmail());
    }

    return $editor;
  }

  private function newTransaction() {
    return $this->getMailReceiver()->getApplicationTransactionTemplate();
  }

  protected function didReceiveMail(
    PhabricatorMetaMTAReceivedMail $mail,
    $body) {
    return array();
  }

  protected function shouldCreateCommentFromMailBody() {
    return (bool)$this->getMailReceiver()->getID();
  }

  final protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $viewer = $this->getActor();
    $object = $this->getMailReceiver();

    $body_data = $mail->parseBody();
    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $xactions = $this->didReceiveMail($mail, $body);

    // If this object is subscribable, subscribe all the users who were
    // CC'd on the message.
    if ($object instanceof PhabricatorSubscribableInterface) {
      $subscriber_phids = $mail->loadCCPHIDs();
      if ($subscriber_phids) {
        $xactions[] = $this->newTransaction()
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(
            array(
              '+' => array($viewer->getPHID()),
            ));
      }
    }

    $command_xactions = $this->processMailCommands(
      $mail,
      $body_data['commands']);
    foreach ($command_xactions as $xaction) {
      $xactions[] = $xaction;
    }

    if ($this->shouldCreateCommentFromMailBody()) {
      $comment = $this
        ->newTransaction()
        ->getApplicationTransactionCommentObject()
        ->setContent($body);

      $xactions[] = $this->newTransaction()
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment($comment);
    }

    $target = $object->getApplicationTransactionObject();

    $this->newEditor($mail)
      ->setContinueOnNoEffect(true)
      ->applyTransactions($target, $xactions);
  }

  private function processMailCommands(
    PhabricatorMetaMTAReceivedMail $mail,
    array $command_list) {

    $viewer = $this->getActor();
    $object = $this->getMailReceiver();

    $list = MetaMTAEmailTransactionCommand::getAllCommandsForObject($object);
    $map = MetaMTAEmailTransactionCommand::getCommandMap($list);

    $xactions = array();
    foreach ($command_list as $command_argv) {
      $command = head($command_argv);
      $argv = array_slice($command_argv, 1);

      $handler = idx($map, phutil_utf8_strtolower($command));
      if ($handler) {
        $results = $handler->buildTransactions(
          $viewer,
          $object,
          $mail,
          $command,
          $argv);
        foreach ($results as $result) {
          $xactions[] = $result;
        }
      } else {
        $valid_commands = array();
        foreach ($list as $valid_command) {
          $aliases = $valid_command->getCommandAliases();
          if ($aliases) {
            foreach ($aliases as $key => $alias) {
              $aliases[$key] = '!'.$alias;
            }
            $aliases = implode(', ', $aliases);
            $valid_commands[] = pht(
              '!%s (or %s)',
              $valid_command->getCommand(),
              $aliases);
          } else {
            $valid_commands[] = '!'.$valid_command->getCommand();
          }
        }

        throw new Exception(
          pht(
            'The command "!%s" is not a supported mail command. Valid '.
            'commands for this object are: %s.',
            $command,
            implode(', ', $valid_commands)));
      }
    }

    return $xactions;
  }

}
