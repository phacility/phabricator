<?php

final class PhabricatorDashboardSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorDashboardDAO');

    $this->buildEdgeSchemata(new PhabricatorDashboard());

    $this->buildTransactionSchema(
      new PhabricatorDashboardTransaction());
    $this->buildTransactionSchema(
      new PhabricatorDashboardPanelTransaction());
  }

}
