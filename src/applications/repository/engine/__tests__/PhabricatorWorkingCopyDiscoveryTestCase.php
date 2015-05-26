<?php

final class PhabricatorWorkingCopyDiscoveryTestCase
  extends PhabricatorWorkingCopyTestCase {

  public function testSubversionCommitDiscovery() {
    $refs = $this->discoverRefs('ST');
    $this->assertEqual(
      array(
        1368319433,
        1368319448,
      ),
      mpull($refs, 'getEpoch'),
      pht('Commit Epochs'));
  }

  public function testMercurialCommitDiscovery() {
    $this->requireBinaryForTest('hg');

    $refs = $this->discoverRefs('HT');
    $this->assertEqual(
      array(
        '4a110ae879f473f2e82ffd032475caedd6cdba91',
      ),
      mpull($refs, 'getIdentifier'));
  }

  public function testGitCommitDiscovery() {
    $refs = $this->discoverRefs('GT');
    $this->assertEqual(
      array(
        '763d4ab372445551c95fb5cccd1a7a223f5b2ac8',
      ),
      mpull($refs, 'getIdentifier'));
  }

  private function discoverRefs($callsign) {
    $repo = $this->buildPulledRepository($callsign);

    $engine = id(new PhabricatorRepositoryDiscoveryEngine())
      ->setRepository($repo);

    $refs = $engine->discoverCommits($repo);

    // The next time through, these should be cached as already discovered.

    $new_refs = $engine->discoverCommits($repo);
    $this->assertEqual(array(), $new_refs);

    return $refs;
  }


}
