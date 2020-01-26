<?php

final class PhabricatorProjectTagsAddedField
  extends PhabricatorProjectTagsField {

  const FIELDCONST = 'projects.added';

  public function getHeraldFieldName() {
    return pht('Project tags added');
  }

  public function getHeraldFieldValue($object) {
    $xaction = $this->getProjectTagsTransaction();
    if (!$xaction) {
      return array();
    }

    $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);

    return $record->getAddedPHIDs();
  }

}
