<?php

final class PhabricatorPhurlSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorPhurlURL());
  }

}
