<?php

final class DifferentialTransaction extends PhabricatorApplicationTransaction {

  const TYPE_INLINE = 'differential:inline';
  const TYPE_UPDATE = 'differential:update';
  const TYPE_ACTION = 'differential:action';

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return DifferentialPHIDTypeRevision::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new DifferentialTransactionComment();
  }

}
