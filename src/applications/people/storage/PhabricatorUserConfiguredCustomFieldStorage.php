<?php

final class PhabricatorUserConfiguredCustomFieldStorage
  extends PhabricatorCustomFieldStorage {

  public function getApplicationName() {
    return 'user';
  }

}
