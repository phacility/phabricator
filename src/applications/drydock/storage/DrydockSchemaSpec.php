<?php

final class DrydockSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('DrydockDAO');

    $this->buildTransactionSchema(
      new DrydockBlueprintTransaction());

  }

}
