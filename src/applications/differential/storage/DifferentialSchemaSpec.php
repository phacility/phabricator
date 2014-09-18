<?php

final class DifferentialSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('DifferentialDAO');
//    $this->addEdgeSchemata($server, new DifferentialRevision());
  }

}
