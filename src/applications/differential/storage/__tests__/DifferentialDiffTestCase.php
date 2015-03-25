<?php

final class DifferentialDiffTestCase extends ArcanistPhutilTestCase {

  public function testDetectCopiedCode() {
    $copies = $this->detectCopiesIn('lint_engine.diff');

    $this->assertEqual(
      array_combine(range(237, 252), range(167, 182)),
      ipull($copies, 1));
  }

  public function testDetectCopiedOverlaidCode() {
    $copies = $this->detectCopiesIn('copy_overlay.diff');

    $this->assertEqual(
      array(
        7 => 22,
        8 => 23,
        9 => 24,
        10 => 25,
        11 => 26,
        12 => 27,
      ),
      ipull($copies, 1));
  }

  private function detectCopiesIn($file) {
    $root = dirname(__FILE__).'/diff/';
    $parser = new ArcanistDiffParser();

    $diff = DifferentialDiff::newFromRawChanges(
      PhabricatorUser::getOmnipotentUser(),
      $parser->parseDiff(Filesystem::readFile($root.$file)));
    return idx(head($diff->getChangesets())->getMetadata(), 'copy:lines');
  }

  public function testDetectSlowCopiedCode() {
    // This tests that the detector has a reasonable runtime when a diff
    // contains a very large number of identical lines. See T5041.

    $parser = new ArcanistDiffParser();

    $line = str_repeat('x', 60);
    $oline = '-'.$line."\n";
    $nline = '+'.$line."\n";

    $n = 1000;
    $oblock = str_repeat($oline, $n);
    $nblock = str_repeat($nline, $n);

    $raw_diff = <<<EODIFF
diff --git a/dst b/dst
new file mode 100644
index 0000000..1234567
--- /dev/null
+++ b/dst
@@ -0,0 +1,{$n} @@
{$nblock}
diff --git a/src b/src
deleted file mode 100644
index 123457..0000000
--- a/src
+++ /dev/null
@@ -1,{$n} +0,0 @@
{$oblock}
EODIFF;

    $diff = DifferentialDiff::newFromRawChanges(
      PhabricatorUser::getOmnipotentUser(),
      $parser->parseDiff($raw_diff));

    $this->assertTrue(true);
  }


}
