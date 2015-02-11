<?php

/**
 * Trivial example test case.
 */
final class PhabricatorTrivialTestCase extends PhabricatorTestCase {

  // NOTE: Update developer/unit_tests.diviner when updating this class!

  private $two;

  protected function willRunOneTest($test_name) {
    // You can execute setup steps which will run before each test in this
    // method.
    $this->two = 2;
  }

  public function testAllIsRightWithTheWorld() {
    $this->assertEqual(4, $this->two + $this->two, '2 + 2 = 4');
  }

}
