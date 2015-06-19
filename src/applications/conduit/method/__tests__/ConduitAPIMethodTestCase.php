<?php

final class ConduitAPIMethodTestCase extends PhabricatorTestCase {

  public function testLoadAllConduitMethods() {
    ConduitAPIMethod::loadAllConduitMethods();
    $this->assertTrue(true);
  }

}
