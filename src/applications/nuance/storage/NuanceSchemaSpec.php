<?php

final class NuanceSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('NuanceDAO');
    $this->buildEdgeSchemata(new NuanceItem());

    $this->buildTransactionSchema(
      new NuanceItemTransaction(),
      new NuanceItemTransactionComment());

    $this->buildTransactionSchema(
      new NuanceQueueTransaction(),
      new NuanceQueueTransactionComment());

    $this->buildTransactionSchema(
      new NuanceRequestorTransaction(),
      new NuanceRequestorTransactionComment());

    $this->buildTransactionSchema(
      new NuanceSourceTransaction(),
      new NuanceSourceTransactionComment());
  }

}
