<?php

final class ManiphestTaskDescriptionHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.description';

  public function getHeraldFieldName() {
    return pht('Description');
  }

  public function getHeraldFieldValue($object) {
    return $object->getDescription();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
