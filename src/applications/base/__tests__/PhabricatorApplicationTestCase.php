<?php

final class PhabricatorApplicationTestCase extends PhabricatorTestCase {

  public function testGetAllApplications() {
    PhabricatorApplication::getAllApplications();
    $this->assertTrue(true);
  }

}
