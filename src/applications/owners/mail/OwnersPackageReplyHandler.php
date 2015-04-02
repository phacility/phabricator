<?php

final class OwnersPackageReplyHandler extends PhabricatorMailReplyHandler {
  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorOwnersPackage)) {
      throw new Exception('Receiver is not a PhabricatorOwnersPackage!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return null;
  }

  public function getPublicReplyHandlerEmailAddress() {
    return null;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    return;
  }
}
