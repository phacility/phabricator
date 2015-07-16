<?php

final class ManiphestTaskAssigneeHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.assignee';

  public function getHeraldFieldName() {
    return pht('Assignee');
  }

  public function getHeraldFieldValue($object) {
    return $object->getOwnerPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_NULLABLE;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
