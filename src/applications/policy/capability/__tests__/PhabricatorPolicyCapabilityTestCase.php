<?php

final class PhabricatorPolicyCapabilityTestCase
  extends PhabricatorTestCase {

  public function testGetCapabilityMap() {
    PhabricatorPolicyCapability::getCapabilityMap();
    $this->assertTrue(true);
  }

}
