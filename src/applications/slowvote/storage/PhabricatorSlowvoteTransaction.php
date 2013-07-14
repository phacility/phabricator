<?php

final class PhabricatorSlowvoteTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME         = 'vote:name';
  const TYPE_DESCRIPTION  = 'vote:description';
  const TYPE_OPTION       = 'vote:option';

  public function getApplicationName() {
    return 'slowvote';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_POLL;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorMacroTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('vote');
  }

}

