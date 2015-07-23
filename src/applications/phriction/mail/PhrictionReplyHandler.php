<?php

final class PhrictionReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhrictionDocument)) {
      throw new Exception(
        pht('Mail receiver is not a %s!', 'PhrictionDocument'));
    }
  }

  public function getObjectPrefix() {
    return PhrictionDocumentPHIDType::TYPECONST;
  }

}
