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

  public function getReplyHandlerDomain() {
    return null;
  }

  public function getReplyHandlerInstructions() {
    return null;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    return;
  }

}
