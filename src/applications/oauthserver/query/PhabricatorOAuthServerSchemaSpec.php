<?php

final class PhabricatorOAuthServerSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorOAuthServerClient());
  }

}
