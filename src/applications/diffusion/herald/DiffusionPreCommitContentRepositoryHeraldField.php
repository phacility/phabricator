<?php

final class DiffusionPreCommitContentRepositoryHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.repository';

  public function getHeraldFieldName() {
    return pht('Repository');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getHookEngine()->getRepository()->getPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return HeraldField::STANDARD_PHID;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_REPOSITORY;
  }

}
