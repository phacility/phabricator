<?php

final class PhabricatorTokenSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorTokenDAO');
  }

}
