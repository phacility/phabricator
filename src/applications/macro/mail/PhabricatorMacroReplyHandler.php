<?php

final class PhabricatorMacroReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorFileImageMacro)) {
      throw new Exception('Mail receiver is not a PhabricatorFileImageMacro!');
    }
  }

  public function getObjectPrefix() {
    return 'MCRO';
  }

}
