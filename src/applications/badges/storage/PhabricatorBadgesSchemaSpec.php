<?php

final class PhabricatorBadgesSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorBadgesBadge());
  }

}
