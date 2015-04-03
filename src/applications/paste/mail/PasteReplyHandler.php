<?php

final class PasteReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorPaste)) {
      throw new Exception('Mail receiver is not a PhabricatorPaste.');
    }
  }

  public function getObjectPrefix() {
    return 'P';
  }

}
