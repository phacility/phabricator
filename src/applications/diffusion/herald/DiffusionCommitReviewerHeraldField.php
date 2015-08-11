<?php

final class DiffusionCommitReviewerHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.reviewer';

  public function getHeraldFieldName() {
    return pht('Reviewer');
  }

  public function getHeraldFieldValue($object) {
    return $object->getCommitData()->getCommitDetail('reviewerPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_NULLABLE;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
