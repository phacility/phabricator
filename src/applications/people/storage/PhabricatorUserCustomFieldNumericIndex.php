<?php

final class PhabricatorUserCustomFieldNumericIndex
  extends PhabricatorCustomFieldNumericIndexStorage {

  public function getApplicationName() {
    return 'user';
  }

}
