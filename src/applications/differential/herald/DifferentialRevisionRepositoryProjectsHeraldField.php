<?php

final class DifferentialRevisionRepositoryProjectsHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.repository.projects';

  public function getHeraldFieldName() {
    return pht('Repository projects');
  }

  public function getHeraldFieldValue($object) {
    $repository = $this->getAdapter()->loadRepository();
    if (!$repository) {
      return array();
    }

    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $repository->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

}
