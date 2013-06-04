<?php

final class DifferentialFreeformFieldTestCase extends PhabricatorTestCase {

  public function testRevertedCommitParser() {
    $map = array(
      "Reverts 123" => array('123'),
      "Reverts r123" => array('r123'),
      "Reverts ac382f2" => array('ac382f2'),
      "Reverts r22, r23" => array('r22', 'r23'),
      "Reverts D99" => array('D99'),
      "Backs out commit\n99\n100" => array('99', '100'),
      "undo change f9f9f8f8" => array('f9f9f8f8'),
      "Backedout Changeset rX1234" => array('rX1234'),
      "This doesn't revert anything" => array(),
      'nonrevert of r11' => array(),
      "fixed a bug" => array(),
    );

    foreach ($map as $input => $expect) {
      $actual = array_values(
        DifferentialFreeformFieldSpecification::findRevertedCommits($input));

      $this->assertEqual(
        $expect,
        $actual,
        "Reverted commits in: {$input}");
    }
  }

}
