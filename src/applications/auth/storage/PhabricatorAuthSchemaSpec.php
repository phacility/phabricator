<?php

final class PhabricatorAuthSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorAuthDAO');
    $this->buildTransactionSchema(
      new PhabricatorAuthProviderConfigTransaction());
  }

}
