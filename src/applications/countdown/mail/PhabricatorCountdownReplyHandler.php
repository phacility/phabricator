<?php

final class PhabricatorCountdownReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorCountdown)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'Countdown'));
    }
  }

  public function getObjectPrefix() {
    return 'C';
  }

}
