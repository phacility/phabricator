<?php

/**
 * @group maniphest
 */
abstract class ManiphestDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'maniphest';
  }

}
