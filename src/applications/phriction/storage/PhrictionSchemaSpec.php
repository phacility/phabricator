<?php

final class PhrictionSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhrictionDAO');

    $this->buildEdgeSchemata(new PhrictionDocument());
  }

}
