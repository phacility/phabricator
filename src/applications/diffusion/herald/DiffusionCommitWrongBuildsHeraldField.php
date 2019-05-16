<?php

final class DiffusionCommitWrongBuildsHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.builds.wrong';

  public function getHeraldFieldName() {
    return pht('Revision has build warning');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();
    $viewer = $adapter->getViewer();

    $revision = $adapter->loadDifferentialRevision();
    if (!$revision) {
      return false;
    }

    if ($revision->isPublished()) {
      $wrong_builds = DifferentialRevision::PROPERTY_WRONG_BUILDS;
      return !$revision->getProperty($wrong_builds, false);
    }

    // Reload the revision to pick up active diffs.
    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($revision->getPHID()))
      ->needActiveDiffs(true)
      ->executeOne();

    $concerning = DifferentialDiffExtractionEngine::loadConcerningBuilds(
      $viewer,
      $revision,
      $strict = false);

    return (bool)$concerning;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_BOOL;
  }

}
