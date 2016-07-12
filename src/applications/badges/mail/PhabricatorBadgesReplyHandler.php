<?php

final class PhabricatorBadgesReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorBadgesBadge)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'Badges'));
    }
  }

  public function getObjectPrefix() {
    return 'BDGE';
  }

}
