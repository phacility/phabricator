<?php

final class PhragmentSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhragmentFragment());
  }

}
