<?php

final class FileReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorFile)) {
      throw new Exception(pht('Mail receiver is not a %s.', 'PhabricatorFile'));
    }
  }

  public function getObjectPrefix() {
    return 'F';
  }

}
