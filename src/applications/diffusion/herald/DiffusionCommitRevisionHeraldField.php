<?php

final class DiffusionCommitRevisionHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.revision';

  public function getHeraldFieldName() {
    return pht('Differential revision');
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->loadDifferentialRevision();

    if (!$revision) {
      return null;
    }

    return $revision->getPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_PHID_BOOL;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_NONE;
  }

}
