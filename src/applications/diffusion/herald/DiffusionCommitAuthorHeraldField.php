<?php

final class DiffusionCommitAuthorHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.author';

  public function getHeraldFieldName() {
    return pht('Author');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getAuthorPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_NULLABLE;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
