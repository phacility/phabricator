<?php

final class FileReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorFile)) {
      throw new Exception('Mail receiver is not a PhabricatorFile.');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'F');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('F');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $actor = $this->getActor();
    $file = $this->getMailReceiver();

    $body_data = $mail->parseBody();
    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $xactions = array();
    $commands = $body_data['commands'];
    foreach ($commands as $command) {
      switch (head($command)) {
        case 'unsubscribe':
          $xaction = id(new PhabricatorFileTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(array('-' => array($actor->getPHID())));
          $xactions[] = $xaction;
          break;
      }
    }

    $xactions[] = id(new PhabricatorFileTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorFileTransactionComment())
          ->setContent($body));

    $editor = id(new PhabricatorFileEditor())
      ->setActor($actor)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setIsPreview(false);

    $editor->applyTransactions($file, $xactions);
  }

}
