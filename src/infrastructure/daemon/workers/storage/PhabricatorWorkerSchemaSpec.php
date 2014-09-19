<?php

final class PhabricatorWorkerSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorWorkerDAO');
    $this->buildCounterSchema(new PhabricatorWorkerActiveTask());
  }

}
