<?php

final class ManiphestTaskTitleHeraldField
  extends ManiphestTaskHeraldField {

  const FIELDCONST = 'maniphest.task.title';

  public function getHeraldFieldName() {
    return pht('Title');
  }

  public function getHeraldFieldValue($object) {
    return $object->getTitle();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}
