<?php

final class LegalpadSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new LegalpadDocument());
  }

}
