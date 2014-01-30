<?php

abstract class PhabricatorDashboardDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'dashboard';
  }

}
