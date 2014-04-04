<?php

abstract class PhabricatorCustomFieldStringIndexStorage
  extends PhabricatorCustomFieldIndexStorage {

  public function formatForInsert(AphrontDatabaseConnection $conn) {
    return qsprintf(
      $conn,
      '(%s, %s, %s)',
      $this->getObjectPHID(),
      $this->getIndexKey(),
      $this->getIndexValue());
  }

  public function getIndexValueType() {
    return 'string';
  }

}
