<?php

final class DiffusionPreCommitContentCommitterHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.committer';

  public function getHeraldFieldName() {
    return pht('Committer');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getCommitterPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_NULLABLE;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
