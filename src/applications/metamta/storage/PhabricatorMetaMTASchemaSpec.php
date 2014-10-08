<?php

final class PhabricatorMetaMTASchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(
      new PhabricatorMetaMTAMail());
  }

}
