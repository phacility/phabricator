<?php

final class PasteReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorPaste)) {
      throw new Exception('Mail receiver is not a PhabricatorPaste.');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'P');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('P');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $actor = $this->getActor();
    $paste = $this->getMailReceiver();

    $body_data = $mail->parseBody();
    $body = $body_data['body'];
    $body = $this->enhanceBodyWithAttachments($body, $mail->getAttachments());

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $lines = explode("\n", trim($body));
    $first_line = head($lines);

    $xactions = array();

    $commands = $body_data['commands'];
    foreach ($commands as $command) {
      switch (head($command)) {
        case 'unsubscribe':
          $xaction = id(new PhabricatorPasteTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(array('-' => array($actor->getPHID())));
          $xactions[] = $xaction;
          break;
      }
    }

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
       id(new PhabricatorPasteTransactionComment())
        ->setContent($body));

    $editor = id(new PhabricatorPasteEditor())
      ->setActor($actor)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setIsPreview(false);

    $editor->applyTransactions($paste, $xactions);
  }

}
