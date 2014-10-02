<?php

final class PhabricatorFileSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorFile());
  }

}
