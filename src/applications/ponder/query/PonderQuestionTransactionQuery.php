<?php

final class PonderQuestionTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PonderQuestionTransaction();
  }

}
