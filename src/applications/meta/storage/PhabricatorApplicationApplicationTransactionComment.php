<?php

final class PhabricatorApplicationApplicationTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorApplicationApplicationTransaction();
  }

}
