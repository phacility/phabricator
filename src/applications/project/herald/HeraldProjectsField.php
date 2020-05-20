<?php

final class HeraldProjectsField
  extends PhabricatorProjectTagsField {

  const FIELDCONST = 'projects';

  public function getHeraldFieldName() {
    return pht('Project tags');
  }

  public function getHeraldFieldValue($object) {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
  }

}
