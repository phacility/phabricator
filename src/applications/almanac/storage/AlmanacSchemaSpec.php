<?php

final class AlmanacSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new AlmanacService());
  }

}
