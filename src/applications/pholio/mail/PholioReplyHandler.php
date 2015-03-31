<?php

final class PholioReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PholioMock)) {
      throw new Exception('Mail receiver is not a PholioMock!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'M');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('M');
  }

  public function getReplyHandlerDomain() {
    return $this->getCustomReplyHandlerDomainIfExists(
      'metamta.pholio.reply-handler-domain');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // TODO: Implement this.
    return null;
  }

}
