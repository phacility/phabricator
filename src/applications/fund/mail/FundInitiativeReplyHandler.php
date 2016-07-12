<?php

final class FundInitiativeReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof FundInitiative)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'FundInitiative'));
    }
  }

  public function getObjectPrefix() {
    return 'I';
  }

}
