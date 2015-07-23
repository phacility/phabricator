<?php

final class PhabricatorPHIDTypeTestCase extends PhutilTestCase {

  public function testGetAllTypes() {
    PhabricatorPHIDType::getAllTypes();
    $this->assertTrue(true);
  }

}
