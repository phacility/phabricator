<?php

final class DiffusionPreCommitRefRepositoryHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.repository';

  public function getHeraldFieldName() {
    return pht('Repository');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getHookEngine()->getRepository()->getPHID();
  }

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new DiffusionRepositoryDatasource();
  }

}
