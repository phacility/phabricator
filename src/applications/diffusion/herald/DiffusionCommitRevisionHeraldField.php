<?php

final class DiffusionCommitRevisionHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.revision';

  public function getHeraldFieldName() {
    return pht('Differential revision');
  }

  public function getFieldGroupKey() {
    return HeraldRelatedFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->loadDifferentialRevision();

    if (!$revision) {
      return null;
    }

    return $revision->getPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_BOOL;
  }

}
