<?php

final class OwnersPackageReplyHandler extends PhabricatorMailReplyHandler {
  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorOwnersPackage)) {
      throw new Exception(
        pht(
          'Receiver is not a %s!',
          'PhabricatorOwnersPackage'));
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorUser $user) {
    return null;
  }

  public function getPublicReplyHandlerEmailAddress() {
    return null;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    return;
  }
}
