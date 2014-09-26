<?php

final class PhabricatorOAuthSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorOAuthServerDAO');
  }

}
