<?php

final class DrydockSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new DrydockBlueprint());
  }

}
