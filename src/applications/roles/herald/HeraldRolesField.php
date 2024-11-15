<?php

final class HeraldRolesField
  extends PhabricatorRoleTagsField {

  const FIELDCONST = 'roles';

  public function getHeraldFieldName() {
    return pht('Role tags');
  }

  public function getHeraldFieldValue($object) {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);
  }

}
