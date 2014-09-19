<?php

final class PhabricatorXHPASTViewSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorXHPASTViewDAO');
  }

}
