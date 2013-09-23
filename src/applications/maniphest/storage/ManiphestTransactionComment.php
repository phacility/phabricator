<?php

final class ManiphestTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getTableName() {
    // TODO: Remove once the "pro" stuff gets dropped.
    return 'maniphest_transaction_comment';
  }

  public function getApplicationTransactionObject() {
    return new ManiphestTransactionPro();
  }

}
