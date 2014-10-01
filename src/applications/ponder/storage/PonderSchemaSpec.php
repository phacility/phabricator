<?php

final class PonderSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PonderDAO');

    $this->buildEdgeSchemata(new PonderQuestion());

    $this->buildTransactionSchema(
      new PonderQuestionTransaction(),
      new PonderQuestionTransactionComment());

    $this->buildTransactionSchema(
      new PonderAnswerTransaction(),
      new PonderAnswerTransactionComment());
  }

}
