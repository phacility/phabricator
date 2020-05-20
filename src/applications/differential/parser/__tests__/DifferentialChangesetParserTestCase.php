<?php

final class DifferentialChangesetParserTestCase extends PhabricatorTestCase {

  public function testDiffChangesets() {
    $hunk = new DifferentialHunk();
    $hunk->setChanges("+a\n b\n-c\n");
    $hunk->setNewOffset(1);
    $hunk->setNewLen(2);
    $left = new DifferentialChangeset();
    $left->attachHunks(array($hunk));

    $tests = array(
      "+a\n b\n-c\n" => array(array(), array()),
      "+a\n x\n-c\n" => array(array(), array()),
      "+aa\n b\n-c\n" => array(array(1), array(11)),
      " b\n-c\n" => array(array(1), array()),
      "+a\n b\n c\n" => array(array(), array(13)),
      "+a\n x\n c\n" => array(array(), array(13)),
    );

    foreach ($tests as $changes => $expected) {
      $hunk = new DifferentialHunk();
      $hunk->setChanges($changes);
      $hunk->setNewOffset(11);
      $hunk->setNewLen(3);
      $right = new DifferentialChangeset();
      $right->attachHunks(array($hunk));

      $parser = new DifferentialChangesetParser();
      $parser->setOriginals($left, $right);
      $this->assertEqual($expected, $parser->diffOriginals(), $changes);
    }
  }

}
