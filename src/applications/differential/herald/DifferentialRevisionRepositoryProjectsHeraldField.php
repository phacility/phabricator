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

  protected function getHeraldFieldStandardConditions() {
    return self::STANDARD_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_PROJECT;
    }
  }

}
