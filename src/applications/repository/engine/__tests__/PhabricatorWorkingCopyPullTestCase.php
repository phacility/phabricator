<?php

final class PhabricatorWorkingCopyPullTestCase
  extends PhabricatorWorkingCopyTestCase {

  public function testGitPullBasic() {
    $repo = $this->buildPulledRepository('GT');

    $this->assertEqual(
      true,
      Filesystem::pathExists($repo->getLocalPath().'/HEAD'));
  }

  public function testHgPullBasic() {
    $repo = $this->buildPulledRepository('HT');

    $this->assertEqual(
      true,
      Filesystem::pathExists($repo->getLocalPath().'/.hg'));
  }

  public function testSVNPullBasic() {
    $repo = $this->buildPulledRepository('ST');

    // We don't pull local clones for SVN, so we don't expect there to be
    // a working copy.
    $this->assertEqual(
      false,
      Filesystem::pathExists($repo->getLocalPath()));
  }

}
