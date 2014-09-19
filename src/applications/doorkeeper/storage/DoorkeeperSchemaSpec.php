<?php

final class DoorkeeperSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('DoorkeeperDAO');

    $this->buildEdgeSchemata(new DoorkeeperExternalObject());
  }

}
