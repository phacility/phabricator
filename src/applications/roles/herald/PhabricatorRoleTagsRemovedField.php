<?php

final class PhabricatorRoleTagsRemovedField
  extends PhabricatorRoleTagsField {

  const FIELDCONST = 'roles.removed';

  public function getHeraldFieldName() {
    return pht('Role tags removed');
  }

  public function getHeraldFieldValue($object) {
    $xaction = $this->getRoleTagsTransaction();
    if (!$xaction) {
      return array();
    }

    $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);

    return $record->getRemovedPHIDs();
  }

}
