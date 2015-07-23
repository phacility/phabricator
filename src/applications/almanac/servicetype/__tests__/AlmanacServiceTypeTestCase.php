<?php

final class AlmanacServiceTypeTestCase extends PhabricatorTestCase {

  public function testGetAllServiceTypes() {
    AlmanacServiceType::getAllServiceTypes();
    $this->assertTrue(true);
  }

}
