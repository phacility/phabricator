<?php

abstract class PhabricatorDraftDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'draft';
  }

}
