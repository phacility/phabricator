<?php

final class PhabricatorChatLogSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorChatLogDAO');
  }

}
