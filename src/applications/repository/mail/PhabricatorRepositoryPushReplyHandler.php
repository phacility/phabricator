<?php

final class PhabricatorRepositoryPushReplyHandler
  extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    return;
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return null;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    return;
  }

}
