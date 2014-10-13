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

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig('metamta.reply-handler-domain');
  }

  public function getReplyHandlerInstructions() {
    if ($this->supportsReplies()) {
      // TODO: Implement.
      return null;
    } else {
      return null;
    }
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // TODO: Implement.
    return null;
  }

}
