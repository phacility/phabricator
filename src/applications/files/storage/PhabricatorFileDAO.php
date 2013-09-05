<?php

/**
 * @group file
 */
abstract class PhabricatorFileDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'file';
  }

}
