<?php

final class ReleephSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('ReleephDAO');

    $this->buildTransactionSchema(
      new ReleephProductTransaction());

    $this->buildTransactionSchema(
      new ReleephBranchTransaction());

    $this->buildTransactionSchema(
      new ReleephRequestTransaction(),
      new ReleephRequestTransactionComment());
  }

}
