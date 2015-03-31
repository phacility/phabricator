<?php

final class PhabricatorMacroReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorFileImageMacro)) {
      throw new Exception('Mail receiver is not a PhabricatorFileImageMacro!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'MCRO');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('MCRO');
  }

  public function getReplyHandlerDomain() {
    return $this->getCustomReplyHandlerDomainIfExists(
      'metamta.macro.reply-handler-domain');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // TODO: Implement this.
    return null;
  }

}
