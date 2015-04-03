<?php

final class PholioReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PholioMock)) {
      throw new Exception('Mail receiver is not a PholioMock!');
    }
  }

  public function getObjectPrefix() {
    return 'M';
  }

}
