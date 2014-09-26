<?php

final class PhabricatorMetaMTASchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorMetaMTADAO');
    $this->buildLiskSchemata('PhabricatorSMSDAO');

    $this->buildEdgeSchemata(
      new PhabricatorMetaMTAMail());
  }

}
