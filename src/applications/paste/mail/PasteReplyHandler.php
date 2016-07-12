<?php

final class PasteReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorPaste)) {
      throw new Exception(
        pht('Mail receiver is not a %s.', 'PhabricatorPaste'));
    }
  }

  public function getObjectPrefix() {
    return 'P';
  }

}
