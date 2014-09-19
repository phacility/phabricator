<?php

final class PhabricatorConduitSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorConduitDAO');
  }

}
