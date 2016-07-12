<?php

final class PhabricatorFileStorageEngineTestCase extends PhabricatorTestCase {

  public function testLoadAllEngines() {
    PhabricatorFileStorageEngine::loadAllEngines();
    $this->assertTrue(true);
  }

}
