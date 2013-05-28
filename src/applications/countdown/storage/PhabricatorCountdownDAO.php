<?php

/**
 * @group countdown
 */
abstract class PhabricatorCountdownDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'countdown';
  }

}
