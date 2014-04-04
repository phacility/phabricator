<?php

final class PonderAnswerTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PonderAnswerTransaction();
  }

}
