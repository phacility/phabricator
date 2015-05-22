<?php

final class PonderQuestionReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PonderQuestion)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'PonderQuestion'));
    }
  }

  public function getObjectPrefix() {
    return 'Q';
  }

}
