<?php

final class PhabricatorSlowvoteReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorSlowvotePoll)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'Slowvote'));
    }
  }

  public function getObjectPrefix() {
    return 'V';
  }

}
