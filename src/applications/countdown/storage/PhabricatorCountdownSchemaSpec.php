<?php

final class PhabricatorCountdownSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorCountdown());
  }

}
