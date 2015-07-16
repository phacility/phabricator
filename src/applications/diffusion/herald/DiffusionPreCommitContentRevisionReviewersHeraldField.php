<?php

final class DiffusionPreCommitContentRevisionReviewersHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.revision.reviewers';

  public function getHeraldFieldName() {
    return pht('Differential reviewers');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->getRevision();

    if (!$revision) {
      return array();
    }

    return $revision->getReviewers();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectOrUserDatasource();
  }

}
