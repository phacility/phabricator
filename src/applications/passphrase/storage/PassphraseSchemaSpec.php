<?php

final class PassphraseSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PassphraseDAO');

    $this->buildTransactionSchema(
      new PassphraseCredentialTransaction());

    $this->buildEdgeSchemata(new PassphraseCredential());
  }

}
