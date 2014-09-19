<?php

final class PhabricatorCountdownSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorCountdownDAO');
  }

}
