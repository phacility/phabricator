<?php

final class PhortuneSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhortuneDAO');

    $this->buildEdgeSchemata(new PhortuneAccount());

    $this->buildTransactionSchema(
      new PhortuneAccountTransaction());

    $this->buildTransactionSchema(
      new PhortuneProductTransaction());
  }

}
