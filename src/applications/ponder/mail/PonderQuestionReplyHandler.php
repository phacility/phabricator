<?php

final class PonderQuestionReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PonderQuestion)) {
      throw new Exception("Mail receiver is not a PonderQuestion!");
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'Q');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('Q');
  }

  public function getReplyHandlerInstructions() {
    return null;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // ignore this entirely for now
  }
}
