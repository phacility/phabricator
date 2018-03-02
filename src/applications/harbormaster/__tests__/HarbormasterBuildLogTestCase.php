<?php

final class HarbormasterBuildLogTestCase
  extends PhabricatorTestCase {

  public function testBuildLogLineMaps() {
    $snowman = "\xE2\x98\x83";

    $inputs = array(
      'no_newlines.log' => array(
        64,
        array(
          str_repeat('AAAAAAAA', 32),
        ),
        array(
          array(64, 0),
          array(128, 0),
          array(192, 0),
          array(255, 0),
        ),
      ),
      'no_newlines_updated.log' => array(
        64,
        array_fill(0, 32, 'AAAAAAAA'),
        array(
          array(64, 0),
          array(128, 0),
          array(192, 0),
        ),
      ),
      'one_newline.log' => array(
        64,
        array(
          str_repeat('AAAAAAAA', 16),
          "\n",
          str_repeat('AAAAAAAA', 16),
        ),
        array(
          array(64, 0),
          array(127, 0),
          array(191, 1),
          array(255, 1),
        ),
      ),
      'several_newlines.log' => array(
        64,
        array_fill(0, 12, "AAAAAAAAAAAAAAAAAA\n"),
        array(
          array(56, 2),
          array(113, 5),
          array(170, 8),
          array(227, 11),
        ),
      ),
      'mixed_newlines.log' => array(
        64,
        array(
          str_repeat('A', 63)."\r",
          str_repeat('A', 63)."\r\n",
          str_repeat('A', 63)."\n",
          str_repeat('A', 63),
        ),
        array(
          array(63, 0),
          array(127, 1),
          array(191, 2),
          array(255, 3),
        ),
      ),
      'more_mixed_newlines.log' => array(
        64,
        array(
          str_repeat('A', 63)."\r",
          str_repeat('A', 62)."\r\n",
          str_repeat('A', 63)."\n",
          str_repeat('A', 63),
        ),
        array(
          array(63, 0),
          array(128, 2),
          array(191, 2),
          array(254, 3),
        ),
      ),
      'emoji.log' => array(
        64,
        array(
          str_repeat($snowman, 64),
        ),
        array(
          array(63, 0),
          array(126, 0),
          array(189, 0),
        ),
      ),
    );

    foreach ($inputs as $label => $input) {
      list($distance, $parts, $expect) = $input;

      $log = id(new HarbormasterBuildLog())
        ->setByteLength(0);

      foreach ($parts as $part) {
        $log->updateLineMap($part, $distance);
      }

      list($actual) = $log->getLineMap();

      $this->assertEqual(
        $expect,
        $actual,
        pht('Line Map for "%s"', $label));
    }
  }

}
