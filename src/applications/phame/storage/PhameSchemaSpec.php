<?php

final class PhameSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhameDAO');
    $this->buildEdgeSchemata(new PhameBlog());
  }

}
