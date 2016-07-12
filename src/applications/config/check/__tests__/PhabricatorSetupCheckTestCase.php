<?php

final class PhabricatorSetupCheckTestCase extends PhabricatorTestCase {

  public function testLoadAllChecks() {
    PhabricatorSetupCheck::loadAllChecks();
    $this->assertTrue(true);
  }

}
