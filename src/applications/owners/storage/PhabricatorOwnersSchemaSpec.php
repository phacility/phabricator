<?php

final class PhabricatorOwnersSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorOwnersPackage());
  }

}
