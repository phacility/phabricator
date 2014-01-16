<?php

final class PhabricatorWorkingCopyDiscoveryTestCase
  extends PhabricatorWorkingCopyTestCase {

  public function testSubversionCommitDiscovery() {
    $repo = $this->buildPulledRepository('ST');

    $engine = id(new PhabricatorRepositoryDiscoveryEngine())
      ->setRepository($repo);

    $refs = $engine->discoverCommits($repo);
    $this->assertEqual(
      array(
        1368319433,
        1368319448,
      ),
      mpull($refs, 'getEpoch'),
      'Commit Epochs');

    // The next time through, these should be cached as already discovered.

    $refs = $engine->discoverCommits($repo);
    $this->assertEqual(array(), $refs);
  }

}
