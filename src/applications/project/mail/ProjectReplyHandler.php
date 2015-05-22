<?php

final class ProjectReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorProject)) {
      throw new Exception(
        pht('Mail receiver is not a %s.', 'PhabricatorProject'));
    }
  }

  public function getObjectPrefix() {
    return PhabricatorProjectProjectPHIDType::TYPECONST;
  }

  protected function shouldCreateCommentFromMailBody() {
    return false;
  }

}
