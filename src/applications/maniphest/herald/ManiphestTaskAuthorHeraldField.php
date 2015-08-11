<?php

final class ManiphestTaskAuthorHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.author';

  public function getHeraldFieldName() {
    return pht('Author');
  }

  public function getHeraldFieldValue($object) {
    return $object->getAuthorPHID();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
