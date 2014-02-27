<?php

final class ManiphestCustomFieldStringIndex
  extends PhabricatorCustomFieldStringIndexStorage {

  public function getApplicationName() {
    return 'maniphest';
  }

}
