<?php

final class FileReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorFile)) {
      throw new Exception('Mail receiver is not a PhabricatorFile.');
    }
  }

  public function getObjectPrefix() {
    return 'F';
  }

}
