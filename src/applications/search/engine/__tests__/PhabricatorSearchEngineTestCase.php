<?php

final class PhabricatorSearchEngineTestCase extends PhabricatorTestCase {

  public function testLoadAllEngines() {
    $services = PhabricatorSearchService::getAllServices();
    $this->assertTrue(!empty($services));
  }

}
