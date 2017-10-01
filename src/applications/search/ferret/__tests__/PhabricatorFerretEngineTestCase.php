<?php

final class PhabricatorFerretEngineTestCase
  extends PhabricatorTestCase {

  public function testTermsCorpus() {
    $map = array(
      'Hear ye, hear ye!' => ' Hear ye hear ye ',
      "Thou whom'st've art worthy." => " Thou whom'st've art worthy ",
      'Guaranteed to contain "food".' => ' Guaranteed to contain food ',
      'http://example.org/path/to/file.jpg' =>
        ' http example org path to file jpg ',
    );

    $engine = new ManiphestTaskFerretEngine();

    foreach ($map as $input => $expect) {
      $actual = $engine->newTermsCorpus($input);

      $this->assertEqual(
        $expect,
        $actual,
        pht('Terms corpus for: %s', $input));
    }
  }

  public function testTermNgramExtraction() {
    $snowman = "\xE2\x98\x83";

    $map = array(
      'a' => array(' a '),
      'ab' => array(' ab', 'ab '),
      'abcdef' => array(' ab', 'abc', 'bcd', 'cde', 'def', 'ef '),
      "{$snowman}" => array(" {$snowman} "),
      "x{$snowman}y" => array(
        " x{$snowman}",
        "x{$snowman}y",
        "{$snowman}y ",
      ),
      "{$snowman}{$snowman}" => array(
        " {$snowman}{$snowman}",
        "{$snowman}{$snowman} ",
      ),
    );

    $engine = new ManiphestTaskFerretEngine();

    foreach ($map as $input => $expect) {
      $actual = $engine->getTermNgramsFromString($input);
      $this->assertEqual(
        $actual,
        $expect,
        pht('Term ngrams for: %s.', $input));
    }
  }

}
