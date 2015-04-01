<?php

final class FundInitiativeReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof FundInitiative)) {
      throw new Exception('Mail receiver is not a FundInitiative!');
    }
  }

  public function getObjectPrefix() {
    return 'I';
  }

}
