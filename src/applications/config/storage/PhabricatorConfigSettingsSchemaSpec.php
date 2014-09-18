<?php

final class PhabricatorConfigSettingsSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorConfigEntryDAO');
    $this->buildTransactionSchema(new PhabricatorConfigTransaction());
  }

}
