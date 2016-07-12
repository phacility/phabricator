<?php

final class PhabricatorFactEngineTestCase extends PhabricatorTestCase {

  public function testLoadAllEngines() {
    PhabricatorFactEngine::loadAllEngines();
    $this->assertTrue(true);
  }

}
