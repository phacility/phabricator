<?php

final class PhamePostReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhamePost)) {
      throw new Exception(
        pht('Mail receiver is not a %s.', 'PhamePost'));
    }
  }

  public function getObjectPrefix() {
    return PhabricatorPhamePostPHIDType::TYPECONST;
  }

}
