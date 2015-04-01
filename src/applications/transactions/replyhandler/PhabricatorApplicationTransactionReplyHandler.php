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

  final protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $viewer = $this->getActor();
    $object = $this->getMailReceiver();

    $body_data = $mail->parseBody();

    $xactions = $this->processMailCommands($body_data['commands']);

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

    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $comment = $this
      ->newTransaction()
      ->getApplicationTransactionCommentObject()
      ->setContent($body);

    $xactions[] = $this->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment($comment);

    $target = $object->getApplicationTransactionObject();

    $this->newEditor($mail)
      ->setContinueOnNoEffect(true)
      ->applyTransactions($target, $xactions);
  }

  protected function processMailCommands(array $commands) {
    // TODO: Modularize this.
    return array();
  }

}
