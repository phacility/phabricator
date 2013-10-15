<?php

/**
 * @group legalpad
 */
final class LegalpadReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof LegalpadDocument)) {
      throw new Exception("Mail receiver is not a LegalpadDocument!");
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'L');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('L');
  }

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig(
      'metamta.reply-handler-domain');
  }

  public function getReplyHandlerInstructions() {
    if ($this->supportsReplies()) {
      return 'Reply to comment or !unsubscribe.';
    } else {
      return null;
    }
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
    $command = $body_data['command'];

    switch ($command) {
      case 'unsubscribe':
        $xaction = id(new LegalpadTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(array('-' => array($actor->getPHID())));
        $xactions[] = $xaction;
        break;
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

    try {
      $xactions = $editor->applyTransactions($document, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      // just do nothing, though unclear why you're sending a blank email
      return true;
    }

    $head_xaction = head($xactions);
    return $head_xaction->getID();
  }

}
