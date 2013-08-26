<?php

final class HeraldRuleTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_EDIT = 'herald:edit';

  public function getApplicationName() {
    return 'herald';
  }

  public function getApplicationTransactionType() {
    return HeraldPHIDTypeRule::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new HeraldRuleTransactionComment();
  }

}

