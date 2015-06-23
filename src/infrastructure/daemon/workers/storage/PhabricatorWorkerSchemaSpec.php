<?php

final class PhabricatorWorkerSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorWorkerBulkJob());
  }

}
