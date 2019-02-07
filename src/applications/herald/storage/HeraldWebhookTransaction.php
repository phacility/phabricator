<?php

final class HeraldWebhookTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'herald';
  }

  public function getApplicationTransactionType() {
    return HeraldWebhookPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'HeraldWebhookTransactionType';
  }

}
