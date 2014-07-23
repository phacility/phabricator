<?php

final class PhabricatorUserTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'user';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPeopleUserPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}
