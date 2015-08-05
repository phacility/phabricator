<?php

final class CelerityPostprocessorTestCase extends PhabricatorTestCase {

  public function testGetAllCelerityPostprocessors() {
    CelerityPostprocessor::getAllPostprocessors();
    $this->assertTrue(true);
  }

}
