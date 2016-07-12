<?php

final class PhabricatorWorkerBulkJobTestCase extends PhabricatorTestCase {

  public function testGetAllBulkJobTypes() {
    PhabricatorWorkerBulkJobType::getAllJobTypes();
    $this->assertTrue(true);
  }

}
