<?php

final class PhabricatorFileTransformTestCase extends PhabricatorTestCase {

  public function testGetAllTransforms() {
    PhabricatorFileTransform::getAllTransforms();
    $this->assertTrue(true);
  }

}
