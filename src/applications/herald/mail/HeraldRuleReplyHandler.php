<?php

final class HeraldRuleReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof HeraldRule)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'HeraldRule'));
    }
  }

  public function getObjectPrefix() {
    return 'H';
  }

}
