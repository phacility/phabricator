<?php

final class DiffusionCommitRevisionAcceptingReviewersHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.revision.accepting';

  public function getHeraldFieldName() {
    return pht('Accepting reviewers');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->loadDifferentialRevision();

    if (!$revision) {
      return array();
    }

    $diff_phid = $revision->getActiveDiffPHID();

    $reviewer_phids = array();
    foreach ($revision->getReviewers() as $reviewer) {
      if ($reviewer->isAccepted($diff_phid)) {
        $reviewer_phids[] = $reviewer->getReviewerPHID();
      }
    }

    return $reviewer_phids;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new DifferentialReviewerDatasource();
  }

}
