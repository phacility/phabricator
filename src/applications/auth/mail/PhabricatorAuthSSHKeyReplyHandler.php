<?php

final class PhabricatorAuthSSHKeyReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorAuthSSHKey)) {
      throw new Exception(
        pht('Mail receiver is not a %s!', 'PhabricatorAuthSSHKey'));
    }
  }

  public function getObjectPrefix() {
    return 'SSHKEY';
  }

}
