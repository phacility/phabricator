<?php

final class LegalpadReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof LegalpadDocument)) {
      throw new Exception('Mail receiver is not a LegalpadDocument!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'L');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('L');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $actor = $this->getActor();
    $document = $this->getMailReceiver();

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
          $xaction = id(new LegalpadTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(array('-' => array($actor->getPHID())));
          $xactions[] = $xaction;
          break;
      }
    }

    $xactions[] = id(new LegalpadTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new LegalpadTransactionComment())
          ->setDocumentID($document->getID())
          ->setLineNumber(0)
          ->setLineLength(0)
          ->setContent($body));

    $editor = id(new LegalpadDocumentEditor())
      ->setActor($actor)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setIsPreview(false);

    $editor->applyTransactions($document, $xactions);
  }

}
