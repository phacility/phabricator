<?php

interface HarbormasterBuildableInterface {

  /**
   * Get the object PHID which best identifies this buildable to humans.
   *
   * This object is the primary object associated with the buildable in the
   * UI. The most human-readable object for a buildable varies: for example,
   * for diffs the container (the revision) is more meaningful than the
   * buildable (the diff), but for commits the buildable (the commit) is more
   * meaningful than the container (the repository).
   *
   * @return phid Related object PHID most meaningful for human viewers.
   */
  public function getHarbormasterBuildableDisplayPHID();

  public function getHarbormasterBuildablePHID();
  public function getHarbormasterContainerPHID();

  public function getBuildVariables();
  public function getAvailableBuildVariables();

  public function newBuildableEngine();

}
