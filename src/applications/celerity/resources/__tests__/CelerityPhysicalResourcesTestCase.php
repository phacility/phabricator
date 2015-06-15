<?php

final class CelerityPhysicalResourcesTestCase extends PhabricatorTestCase {

  public function testGetAll() {
    CelerityPhysicalResources::getAll();
    $this->assertTrue(true);
  }

}
