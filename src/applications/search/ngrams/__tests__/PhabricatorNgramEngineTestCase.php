<?php

final class PhabricatorNgramEngineTestCase
  extends PhabricatorTestCase {

  public function testTermsCorpus() {
    $map = array(
      'Hear ye, hear ye!' => 'Hear ye hear ye',
      "Thou whom'st've art worthy." => "Thou whom'st've art worthy",
      'Guaranteed to contain "food".' => 'Guaranteed to contain food',
      'http://example.org/path/to/file.jpg' =>
        'http example org path to file jpg',
    );

    $engine = new PhabricatorNgramEngine();
    foreach ($map as $input => $expect) {
      $actual = $engine->newTermsCorpus($input);

      $this->assertEqual(
        $expect,
        $actual,
        pht('Terms corpus for: %s', $input));
    }
  }

}
