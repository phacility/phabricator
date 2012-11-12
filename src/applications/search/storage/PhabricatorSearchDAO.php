<?php

/**
 * @group search
 */
abstract class PhabricatorSearchDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'search';
  }

}
