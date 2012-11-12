<?php

abstract class PhabricatorTimelineDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'timeline';
  }

}
