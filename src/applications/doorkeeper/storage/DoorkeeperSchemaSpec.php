<?php

final class DoorkeeperSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new DoorkeeperExternalObject());
  }

}
