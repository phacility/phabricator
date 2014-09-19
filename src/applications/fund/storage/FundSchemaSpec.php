<?php

final class FundSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('FundDAO');
    $this->buildEdgeSchemata(new FundInitiative());

    $this->buildTransactionSchema(
      new FundInitiativeTransaction());

    $this->buildTransactionSchema(
      new FundBackerTransaction());
  }

}
