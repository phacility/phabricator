<?php

final class PhabricatorSlowvoteSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorSlowvoteDAO');
    $this->buildEdgeSchemata(new PhabricatorSlowvotePoll());
    $this->buildTransactionSchema(
      new PhabricatorSlowvoteTransaction(),
      new PhabricatorSlowvoteTransactionComment());
  }

}
