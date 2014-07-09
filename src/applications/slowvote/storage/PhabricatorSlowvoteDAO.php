<?php

abstract class PhabricatorSlowvoteDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'slowvote';
  }

}
