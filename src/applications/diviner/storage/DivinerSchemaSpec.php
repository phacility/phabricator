<?php

final class DivinerSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new DivinerLiveBook());
  }

}
