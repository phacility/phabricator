<?php

final class PhabricatorRepositoryPushReplyHandler
  extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    return;
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorUser $user) {
    return null;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    return;
  }

}
