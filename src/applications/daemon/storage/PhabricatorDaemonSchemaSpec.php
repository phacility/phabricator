<?php

final class PhabricatorDaemonSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorDaemonDAO');
  }

}
