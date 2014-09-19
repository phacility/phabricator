<?php

final class LegalpadSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('LegalpadDAO');
    $this->buildEdgeSchemata(new LegalpadDocument());

    $this->buildTransactionSchema(
      new LegalpadTransaction(),
      new LegalpadTransactionComment());
  }

}
