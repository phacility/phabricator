<?php

final class PonderAnswerTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PonderAnswerTransaction();
  }

}
