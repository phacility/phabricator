<?php

final class PhluxSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhluxDAO');
    $this->buildTransactionSchema(new PhluxTransaction());
  }

}
