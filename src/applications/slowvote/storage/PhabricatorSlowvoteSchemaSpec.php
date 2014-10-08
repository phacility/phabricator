<?php

final class PhabricatorSlowvoteSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorSlowvotePoll());
  }

}
