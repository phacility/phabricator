<?php

final class DiffusionPreCommitContentRevisionAcceptingReviewersHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.revision.accepting';

  public function getHeraldFieldName() {
    return pht('Accepting reviewers');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->getRevision();

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
