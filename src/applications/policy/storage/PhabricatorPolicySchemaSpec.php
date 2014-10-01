<?php

final class PhabricatorPolicySchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorPolicyDAO');
  }

}
