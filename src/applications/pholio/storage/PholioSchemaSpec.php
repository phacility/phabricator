<?php

final class PholioSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PholioDAO');

    $this->buildEdgeSchemata(new PholioMock());

    $this->buildTransactionSchema(
      new PholioTransaction(),
      new PholioTransactionComment());
  }

}
