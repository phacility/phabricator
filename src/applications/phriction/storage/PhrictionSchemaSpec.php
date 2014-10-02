<?php

final class PhrictionSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhrictionDocument());
  }

}
