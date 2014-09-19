<?php

final class PhabricatorAuditSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildTransactionSchema(
      new PhabricatorAuditTransaction(),
      new PhabricatorAuditTransactionComment());
  }

}
