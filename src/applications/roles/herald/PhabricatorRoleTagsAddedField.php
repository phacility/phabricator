<?php

final class PhabricatorRoleTagsAddedField
  extends PhabricatorRoleTagsField {

  const FIELDCONST = 'roles.added';

  public function getHeraldFieldName() {
    return pht('Role tags added');
  }

  public function getHeraldFieldValue($object) {
    $xaction = $this->getRoleTagsTransaction();
    if (!$xaction) {
      return array();
    }

    $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);

    return $record->getAddedPHIDs();
  }

}
