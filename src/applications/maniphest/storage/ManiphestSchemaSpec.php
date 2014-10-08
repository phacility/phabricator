<?php

final class ManiphestSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new ManiphestTask());
  }

}
