<?php

final class PhabricatorFlagSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorFlagDAO');
  }

}
