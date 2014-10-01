<?php

final class PhabricatorFactSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorFactDAO');
  }

}
