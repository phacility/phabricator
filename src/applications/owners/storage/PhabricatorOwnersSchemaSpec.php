<?php

final class PhabricatorOwnersSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorOwnersDAO');
  }

}
