<?php

final class PhrictionTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhrictionTransaction();
  }

}
