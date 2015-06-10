<?php

final class PhabricatorSpacesSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorSpacesNamespace());
  }

}
