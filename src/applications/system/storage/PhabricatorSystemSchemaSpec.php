<?php

final class PhabricatorSystemSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorSystemDAO');
  }

}
