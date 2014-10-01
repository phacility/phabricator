<?php

final class PhabricatorSearchSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorSearchDAO');
  }

}
