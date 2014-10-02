<?php

final class PassphraseSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PassphraseCredential());
  }

}
