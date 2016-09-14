<?php

final class PhabricatorFileTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorFileTransaction();
  }

}
