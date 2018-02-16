<?php

final class DifferentialHunkParserTestCase extends PhabricatorTestCase {

  private function createComment() {
    $comment = new DifferentialInlineComment();
    return $comment;
  }

  private function createHunk(
    $old_offset,
    $old_len,
    $new_offset,
    $new_len,
    $changes) {

    $hunk = id(new DifferentialHunk())
      ->setOldOffset($old_offset)
      ->setOldLen($old_len)
      ->setNewOffset($new_offset)
      ->setNewLen($new_len)
      ->setChanges($changes);

    return $hunk;
  }

  // Returns a change that consists of a single hunk, starting at line 1.
  private function createSingleChange($old_lines, $new_lines, $changes) {
    return array(
      0 => $this->createHunk(1, $old_lines, 1, $new_lines, $changes),
    );
  }

  private function createHunksFromFile($name) {
    $data = Filesystem::readFile(dirname(__FILE__).'/data/'.$name);

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($data);
    if (count($changes) !== 1) {
      throw new Exception(pht("Expected 1 changeset for '%s'!", $name));
    }

    $diff = DifferentialDiff::newFromRawChanges(
      PhabricatorUser::getOmnipotentUser(),
      $changes);
    return head($diff->getChangesets())->getHunks();
  }

  public function testOneLineOldComment() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(1, 0, '-a');
    $context = $parser->makeContextDiff(
      $hunks,
      0,
      1,
      0,
      0);
    $this->assertEqual("@@ -1,1 @@\n-a", $context);
  }

  public function testOneLineNewComment() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(0, 1, '+a');
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      1,
      0,
      0);
    $this->assertEqual("@@ +1,1 @@\n+a", $context);
  }

  public function testCannotFindContext() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(0, 1, '+a');
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      2,
      0,
      0);
    $this->assertEqual('', $context);
  }

  public function testOverlapFromStartOfHunk() {
    $parser = new DifferentialHunkParser();
    $hunks = array(
      0 => $this->createHunk(23, 2, 42, 2, " 1\n 2"),
    );
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      41,
      1,
      0);
    $this->assertEqual("@@ -23,1 +42,1 @@\n 1", $context);
  }

  public function testOverlapAfterEndOfHunk() {
    $parser = new DifferentialHunkParser();
    $hunks = array(
      0 => $this->createHunk(23, 2, 42, 2, " 1\n 2"),
    );
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      43,
      1,
      0);
    $this->assertEqual("@@ -24,1 +43,1 @@\n 2", $context);
  }

  public function testInclusionOfNewFileInOldCommentFromStart() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(2, 3,
      "+n1\n".
      " e1/2\n".
      "-o2\n".
      "+n3\n");
    $context = $parser->makeContextDiff(
      $hunks,
      0,
      1,
      1,
      0);
    $this->assertEqual(
      "@@ -1,2 +2,1 @@\n".
      " e1/2\n".
      "-o2", $context);
  }

  public function testInclusionOfOldFileInNewCommentFromStart() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(2, 2,
      "-o1\n".
      " e2/1\n".
      "-o3\n".
      "+n2\n");
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      1,
      1,
      0);
    $this->assertEqual(
      "@@ -2,1 +1,2 @@\n".
      " e2/1\n".
      "+n2", $context);
  }

  public function testNoNewlineAtEndOfFile() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(0, 1,
      "+a\n".
      "\\No newline at end of file");
    // Note that this only works with additional context.
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      2,
      0,
      1);
    $this->assertEqual(
      "@@ +1,1 @@\n".
      "+a\n".
      "\\No newline at end of file", $context);
  }

  public function testMultiLineNewComment() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(7, 7,
      " e1\n".
      " e2\n".
      "-o3\n".
      "-o4\n".
      "+n3\n".
      " e5/4\n".
      " e6/5\n".
      "+n6\n".
      " e7\n");
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      2,
      4,
      0);
    $this->assertEqual(
      "@@ -2,5 +2,5 @@\n".
      " e2\n".
      "-o3\n".
      "-o4\n".
      "+n3\n".
      " e5/4\n".
      " e6/5\n".
      "+n6", $context);
  }

  public function testMultiLineOldComment() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(7, 7,
      " e1\n".
      " e2\n".
      "-o3\n".
      "-o4\n".
      "+n3\n".
      " e5/4\n".
      " e6/5\n".
      "+n6\n".
      " e7\n");
    $context = $parser->makeContextDiff(
      $hunks,
      0,
      2,
      4,
      0);
    $this->assertEqual(
      "@@ -2,5 +2,4 @@\n".
      " e2\n".
      "-o3\n".
      "-o4\n".
      "+n3\n".
      " e5/4\n".
      " e6/5", $context);
  }

  public function testInclusionOfNewFileInOldCommentFromStartWithContext() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(2, 3,
      "+n1\n".
      " e1/2\n".
      "-o2\n".
      "+n3\n");
    $context = $parser->makeContextDiff(
      $hunks,
      0,
      1,
      1,
      1);
    $this->assertEqual(
      "@@ -1,2 +1,2 @@\n".
      "+n1\n".
      " e1/2\n".
      "-o2", $context);
  }

  public function testInclusionOfOldFileInNewCommentFromStartWithContext() {
    $parser = new DifferentialHunkParser();
    $hunks = $this->createSingleChange(2, 2,
      "-o1\n".
      " e2/1\n".
      "-o3\n".
      "+n2\n");
    $context = $parser->makeContextDiff(
      $hunks,
      1,
      1,
      1,
      1);
    $this->assertEqual(
      "@@ -1,3 +1,2 @@\n".
      "-o1\n".
      " e2/1\n".
      "-o3\n".
      "+n2", $context);
  }

  public function testMissingContext() {
    $tests = array(
      'missing_context.diff' => array(
        4 => true,
      ),
      'missing_context_2.diff' => array(
        5 => true,
      ),
      'missing_context_3.diff' => array(
        4 => true,
        13 => true,
      ),
    );

    foreach ($tests as $name => $expect) {
      $hunks = $this->createHunksFromFile($name);

      $parser = new DifferentialHunkParser();
      $actual = $parser->getHunkStartLines($hunks);

      $this->assertEqual($expect, $actual, $name);
    }
  }

}
