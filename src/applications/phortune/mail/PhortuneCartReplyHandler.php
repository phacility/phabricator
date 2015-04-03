<?php

final class PhortuneCartReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhortuneCart)) {
      throw new Exception('Mail receiver is not a PhortuneCart!');
    }
  }

  public function getObjectPrefix() {
    return 'CART';
  }

}
