<?php

final class PhabricatorMetaMTAApplicationEmailTransaction
  extends PhabricatorApplicationTransaction {

  const KEY_CONFIG = 'appemail.config.key';

  const TYPE_ADDRESS = 'appemail.address';
  const TYPE_CONFIG = 'appemail.config';

  public function getApplicationName() {
    return 'metamta';
  }

  public function getApplicationTransactionType() {
    return PhabricatorMetaMTAApplicationEmailPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}
