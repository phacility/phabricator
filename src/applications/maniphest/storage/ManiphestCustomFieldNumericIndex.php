<?php

final class ManiphestCustomFieldNumericIndex
  extends PhabricatorCustomFieldNumericIndexStorage {

  public function getApplicationName() {
    return 'maniphest';
  }

}
