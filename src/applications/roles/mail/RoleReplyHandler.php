<?php

final class RoleReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorRole)) {
      throw new Exception(
        pht('Mail receiver is not a %s.', 'PhabricatorRole'));
    }
  }

  public function getObjectPrefix() {
    return PhabricatorRoleRolePHIDType::TYPECONST;
  }

  protected function shouldCreateCommentFromMailBody() {
    return false;
  }

}
