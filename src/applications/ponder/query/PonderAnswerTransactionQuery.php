<?php

final class PonderAnswerTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PonderAnswerTransaction();
  }

}
