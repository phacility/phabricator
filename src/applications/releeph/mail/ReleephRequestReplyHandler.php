<?php

final class ReleephRequestReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ReleephRequest)) {
      throw new Exception('Mail receiver is not a ReleephRequest!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'Y');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('Y');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $rq = $this->getMailReceiver();
    $user = $this->getActor();

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    $body = $mail->getCleanTextBody();

    $xactions = array();
    $xactions[] = id(new ReleephRequestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment($body);

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($user)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setParentMessageID($mail->getMessageID());

    $editor->applyTransactions($rq, $xactions);
  }

}
