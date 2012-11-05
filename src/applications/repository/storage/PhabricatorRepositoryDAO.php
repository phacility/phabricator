<?php

abstract class PhabricatorRepositoryDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'repository';
  }

}
