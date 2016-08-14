<?php

final class PhabricatorRepositoryCommitTestCase
  extends PhabricatorTestCase {

  public function testSummarizeCommits() {
    // Cyrillic "zhe".
    $zhe = "\xD0\xB6";

    // Symbol "Snowman".
    $snowman = "\xE2\x98\x83";

    // Emoji "boar".
    $boar = "\xF0\x9F\x90\x97";

    // Proper unicode truncation is tested elsewhere, this is just making
    // sure column length handling is sane.

    $map = array(
      '' => 0,
      'a' => 1,
      str_repeat('a', 81) => 82,
      str_repeat('a', 255) => 82,
      str_repeat('aa ', 30) => 80,
      str_repeat($zhe, 300) => 161,
      str_repeat($snowman, 300) => 240,
      str_repeat($boar, 300) => 255,
    );

    foreach ($map as $input => $expect) {
      $actual = PhabricatorRepositoryCommitData::summarizeCommitMessage(
        $input);
      $this->assertEqual($expect, strlen($actual));
    }

  }

}
