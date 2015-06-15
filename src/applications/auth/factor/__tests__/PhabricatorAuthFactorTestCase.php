<?php

final class PhabricatorAuthFactorTestCase extends PhabricatorTestCase {

  public function testGetAllFactors() {
    PhabricatorAuthFactor::getAllFactors();
    $this->assertTrue(true);
  }

}
