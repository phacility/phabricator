<?php

final class PhrequentSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhrequentDAO');
  }

}
