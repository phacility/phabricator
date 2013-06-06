<?php

final class PhabricatorUserTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'user';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_USER;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationObjectTypeName() {
    return pht('user');
  }

}

