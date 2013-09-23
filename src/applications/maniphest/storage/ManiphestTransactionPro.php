<?php

final class ManiphestTransactionPro
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'maniphest';
  }

  public function getApplicationTransactionType() {
    return ManiphestPHIDTypeTask::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ManiphestTransactionComment();
  }

}

