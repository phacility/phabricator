<?php

final class ManiphestCustomFieldStorage
  extends PhabricatorCustomFieldStorage {

  public function getApplicationName() {
    return 'maniphest';
  }

}
