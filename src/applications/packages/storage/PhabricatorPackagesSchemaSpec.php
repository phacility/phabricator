<?php

final class PhabricatorPackagesSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorPackagesPublisher());
  }

}
