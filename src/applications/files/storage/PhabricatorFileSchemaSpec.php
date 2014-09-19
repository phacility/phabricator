<?php

final class PhabricatorFileSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorFileDAO');

    $this->buildEdgeSchemata(new PhabricatorFile());

    $this->buildTransactionSchema(
      new PhabricatorFileTransaction(),
      new PhabricatorFileTransactionComment());

    $this->buildTransactionSchema(
      new PhabricatorMacroTransaction(),
      new PhabricatorMacroTransactionComment());
  }

}
