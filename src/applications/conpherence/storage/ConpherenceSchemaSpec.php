<?php

final class ConpherenceSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('ConpherenceDAO');

    $this->buildEdgeSchemata(new ConpherenceThread());

    $this->buildTransactionSchema(
      new ConpherenceTransaction(),
      new ConpherenceTransactionComment());
  }

}
