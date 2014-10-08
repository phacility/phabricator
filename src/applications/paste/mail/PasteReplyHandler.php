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

  public function getReplyHandlerInstructions() {
    if ($this->supportsReplies()) {
      return pht('Reply to comment or !unsubscribe.');
    } else {
      return null;
    }
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
    $command = $body_data['command'];

    switch ($command) {
      case 'unsubscribe':
        $xaction = id(new PhabricatorPasteTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(array('-' => array($actor->getPHID())));
        $xactions[] = $xaction;
        break;
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

    try {
      $xactions = $editor->applyTransactions($paste, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      // just do nothing, though unclear why you're sending a blank email
      return true;
    }

    $head_xaction = head($xactions);
    return $head_xaction->getID();
  }

}
