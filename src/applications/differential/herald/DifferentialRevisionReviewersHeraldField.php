<?php

final class DifferentialRevisionReviewersHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.reviewers';

  public function getHeraldFieldName() {
    return pht('Reviewers');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->loadReviewers();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectOrUserDatasource();
  }

}
