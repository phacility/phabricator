<?php

final class PhabricatorPasteSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorPasteDAO');

    $this->buildTransactionSchema(
      new PhabricatorPasteTransaction(),
      new PhabricatorPasteTransactionComment());

    $this->buildEdgeSchemata(new PhabricatorPaste());
  }

}
