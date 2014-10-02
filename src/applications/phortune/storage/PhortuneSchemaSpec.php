<?php

final class PhortuneSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhortuneAccount());
  }

}
