<?php

final class PhabricatorApplicationSearchEngineTestCase
  extends PhabricatorTestCase {

  public function testGetAllEngines() {
    PhabricatorApplicationSearchEngine::getAllEngines();
    $this->assertTrue(true);
  }

}
