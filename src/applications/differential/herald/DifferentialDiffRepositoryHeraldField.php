<?php

final class DifferentialDiffRepositoryHeraldField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.repository';

  public function getHeraldFieldName() {
    return pht('Repository');
  }

  public function getHeraldFieldValue($object) {
    $repository = $this->getAdapter()->loadRepository();

    if (!$repository) {
      return null;
    }

    return $repository->getPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_NULLABLE;
  }

  protected function getDatasource() {
    return new DiffusionRepositoryDatasource();
  }

}
