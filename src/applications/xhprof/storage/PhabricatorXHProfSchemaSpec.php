<?php

final class PhabricatorXHProfSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorXHProfDAO');
  }

}
