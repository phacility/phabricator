<?php

final class ConpherenceSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new ConpherenceThread());
  }

}
