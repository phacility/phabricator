<?php

/**
 * @group phame
 */
abstract class PhameDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'phame';
  }

}
