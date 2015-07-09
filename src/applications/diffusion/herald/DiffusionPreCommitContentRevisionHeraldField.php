<?php

final class DiffusionPreCommitContentRevisionHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.revision';

  public function getHeraldFieldName() {
    return pht('Differential revision');
  }

  public function getHeraldFieldValue($object) {
    $revision = $this->getAdapter()->getRevision();

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
