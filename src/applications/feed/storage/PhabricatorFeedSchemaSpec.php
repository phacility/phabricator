<?php

final class PhabricatorFeedSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorFeedDAO');
  }

}
