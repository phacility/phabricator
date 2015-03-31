<?php

final class FundInitiativeReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof FundInitiative)) {
      throw new Exception('Mail receiver is not a FundInitiative!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'I');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('I');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // TODO: Implement.
    return null;
  }

}
