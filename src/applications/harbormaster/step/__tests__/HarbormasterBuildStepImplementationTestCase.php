<?php

final class HarbormasterBuildStepImplementationTestCase
  extends PhabricatorTestCase {

  public function testGetImplementations() {
    HarbormasterBuildStepImplementation::getImplementations();
    $this->assertTrue(true);
  }

}
