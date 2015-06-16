<?php

final class DrydockBlueprintImplementationTestCase extends PhabricatorTestCase {

  public function testGetAllBlueprintImplementations() {
    DrydockBlueprintImplementation::getAllBlueprintImplementations();
    $this->assertTrue(true);
  }

}
