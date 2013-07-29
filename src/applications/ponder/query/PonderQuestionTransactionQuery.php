<?php

final class PonderQuestionTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PonderQuestionTransaction();
  }

}
