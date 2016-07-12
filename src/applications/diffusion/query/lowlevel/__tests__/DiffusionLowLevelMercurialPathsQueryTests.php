<?php

final class DiffusionLowLevelMercurialPathsQueryTests
  extends PhabricatorTestCase {

  public function testCommandByVersion() {
    $cases = array(
      array(
        'name' => pht('Versions which should not use `files`'),
        'versions' => array('2.6.2', '2.9', '3.1'),
        'match' => false,
      ),

      array(
        'name' => pht('Versions which should use `files`'),
        'versions' => array('3.2', '3.3', '3.5.2'),
        'match' => true,
      ),
    );

    foreach ($cases as $case) {
      foreach ($case['versions'] as $version) {
        $actual = PhabricatorRepositoryVersion
          ::isMercurialFilesCommandAvailable($version);
        $expect = $case['match'];
        $this->assertEqual($expect, $actual, $case['name']);
      }
    }
  }

}
