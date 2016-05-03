<?php

final class PhabricatorSearchSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorProfilePanelConfiguration());
  }

}
