<?php

final class PhabricatorEdgeTypeTestCase extends PhabricatorTestCase {

  public function testGetAllTypes() {
    PhabricatorEdgeType::getAllTypes();
    $this->assertTrue(true);
  }

}
