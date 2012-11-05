<?php

abstract class PhabricatorFeedDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'feed';
  }

}
