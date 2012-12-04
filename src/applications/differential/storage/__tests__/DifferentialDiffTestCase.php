<?php

final class DifferentialDiffTestCase extends ArcanistPhutilTestCase {

  public function testDetectCopiedCode() {
    $root = dirname(__FILE__).'/diff/';
    $parser = new ArcanistDiffParser();

    $diff = DifferentialDiff::newFromRawChanges(
      $parser->parseDiff(Filesystem::readFile($root.'lint_engine.diff')));
    $copies = idx(head($diff->getChangesets())->getMetadata(), 'copy:lines');

    $this->assertEqual(
      array_combine(range(237, 252), range(167, 182)),
      ipull($copies, 1));
  }

}
