<?php

final class PhabricatorOwnersCustomFieldNumericIndex
  extends PhabricatorCustomFieldNumericIndexStorage {

  public function getApplicationName() {
    return 'owners';
  }

}
