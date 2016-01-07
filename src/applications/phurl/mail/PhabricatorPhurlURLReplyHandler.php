<?php

final class PhabricatorPhurlURLReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorPhurlURL)) {
      throw new Exception(
        pht(
          'Mail receiver is not a %s!',
          'PhabricatorPhurlURL'));
    }
  }

  public function getObjectPrefix() {
    return 'U';
  }

}
