<?php

/**
 * Support for Buildkite.
 */
interface HarbormasterBuildkiteBuildableInterface {

  public function getBuildkiteBranch();
  public function getBuildkiteCommit();

}
