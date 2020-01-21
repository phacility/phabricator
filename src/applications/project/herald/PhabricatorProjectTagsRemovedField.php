<?php

final class PhabricatorProjectTagsRemovedField
  extends PhabricatorProjectTagsField {

  const FIELDCONST = 'projects.removed';

  public function getHeraldFieldName() {
    return pht('Project tags removed');
  }

  public function getHeraldFieldValue($object) {
    $xaction = $this->getProjectTagsTransaction();
    if (!$xaction) {
      return array();
    }

    $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);

    return $record->getRemovedPHIDs();
  }

}
