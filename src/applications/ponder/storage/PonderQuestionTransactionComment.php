<?php

final class PonderQuestionTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PonderQuestionTransaction();
  }

}
