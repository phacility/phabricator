<?php

abstract class PhabricatorCustomFieldNumericIndexStorage
  extends PhabricatorCustomFieldIndexStorage {

  public function formatForInsert(AphrontDatabaseConnection $conn) {
    return qsprintf(
      $conn,
      '(%s, %s, %d)',
      $this->getObjectPHID(),
      $this->getIndexKey(),
      $this->getIndexValue());
  }

  public function getIndexValueType() {
    return 'int';
  }

}
