<?php

final class PhortuneCartReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhortuneCart)) {
      throw new Exception('Mail receiver is not a PhortuneCart!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'CART');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('CART');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    // TODO: Implement.
    return null;
  }

}
