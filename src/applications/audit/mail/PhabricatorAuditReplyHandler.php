<?php

final class PhabricatorAuditReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorRepositoryCommit)) {
      throw new Exception('Mail receiver is not a commit!');
    }
  }

  public function getObjectPrefix() {
    // TODO: This conflicts with Countdown and will probably need to be
    // changed eventually.
    return 'C';
  }

}
