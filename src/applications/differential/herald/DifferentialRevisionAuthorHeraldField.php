<?php

final class DifferentialRevisionAuthorHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.author';

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
