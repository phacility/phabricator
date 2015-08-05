<?php

final class DiffusionCommitCommitterHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.committer';

  public function getHeraldFieldName() {
    return pht('Committer');
  }

  public function getHeraldFieldValue($object) {
    return $object->getCommitData()->getCommitDetail('committerPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_NULLABLE;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
