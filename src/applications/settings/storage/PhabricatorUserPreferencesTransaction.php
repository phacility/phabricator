<?php

final class PhabricatorUserPreferencesTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'user';
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationTransactionType() {
    return PhabricatorUserPreferencesPHIDType::TYPECONST;
  }

}
