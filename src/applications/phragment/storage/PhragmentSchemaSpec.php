<?php

final class PhragmentSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhragmentDAO');

    $this->buildEdgeSchemata(new PhragmentFragment());
  }

}
