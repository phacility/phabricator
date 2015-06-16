<?php

final class PhabricatorSearchEngineTestCase extends PhabricatorTestCase {

  public function testLoadAllEngines() {
    PhabricatorSearchEngine::loadAllEngines();
    $this->assertTrue(true);
  }

}
