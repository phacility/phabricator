<?php

final class HeraldProjectsField extends HeraldField {

  const FIELDCONST = 'projects';

  public function getHeraldFieldName() {
    return pht('Projects');
  }

  public function getFieldGroupKey() {
    return HeraldSupportFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function getHeraldFieldValue($object) {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

}
