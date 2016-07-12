<?php

final class PhabricatorSearchEngineTestCase extends PhabricatorTestCase {

  public function testLoadAllEngines() {
    PhabricatorFulltextStorageEngine::loadAllEngines();
    $this->assertTrue(true);
  }

}
