<?php

final class ProjectReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorProject)) {
      throw new Exception('Mail receiver is not a PhabricatorProject.');
    }
  }

  public function getObjectPrefix() {
    return PhabricatorProjectProjectPHIDType::TYPECONST;
  }

  protected function shouldCreateCommentFromMailBody() {
    return false;
  }

}
