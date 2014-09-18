<?php

final class PhabricatorDraftSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorDraftDAO');
  }

}
