<?php

final class PholioReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PholioMock)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'PholioMock'));
    }
  }

  public function getObjectPrefix() {
    return 'M';
  }

}
