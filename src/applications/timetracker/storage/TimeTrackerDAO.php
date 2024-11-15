<?php

abstract class TimeTrackerDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'timetracker';
  }
}
