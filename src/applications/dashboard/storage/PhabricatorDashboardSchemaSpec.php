<?php

final class PhabricatorDashboardSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorDashboard());
  }

}
