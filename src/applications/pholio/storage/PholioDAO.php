<?php

/**
 * @group pholio
 */
abstract class PholioDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'pholio';
  }

}
