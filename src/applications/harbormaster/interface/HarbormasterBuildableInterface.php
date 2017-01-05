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


  /**
   * Get the object PHID which build status should be published to.
   *
   * In some cases (like commits), this is the object itself. In other cases,
   * it is a different object: for example, diffs publish builds to revisions.
   *
   * This method can return `null` to disable publishing.
   *
   * @return phid|null Build status updates will be published to this object's
   *  transaction timeline.
   */
  public function getHarbormasterPublishablePHID();


  public function getBuildVariables();
  public function getAvailableBuildVariables();

}
