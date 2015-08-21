<?php

final class PonderAnswerReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PonderAnswer)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'PonderAnswer'));
    }
  }

  public function getObjectPrefix() {
    return 'ANSR';
  }

}
