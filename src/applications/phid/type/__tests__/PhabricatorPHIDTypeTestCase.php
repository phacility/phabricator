<?php

final class PhabricatorPHIDTypeTestCase extends PhutilTestCase {

  public function testGetAllTypes() {
    PhabricatorPHIDType::getAllTypes();
    $this->assertTrue(true);
  }

  public function testGetPHIDTypeApplicationClass() {
    $types = PhabricatorPHIDType::getAllTypes();

    foreach ($types as $type) {
      $application_class = $type->getPHIDTypeApplicationClass();

      if ($application_class !== null) {
        $this->assertTrue(class_exists($application_class));
      }
    }
  }

}
