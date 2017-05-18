<?php

final class PhabricatorApplicationSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorApplicationsApplication());
  }

}
