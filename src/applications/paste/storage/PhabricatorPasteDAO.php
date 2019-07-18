<?php

abstract class PhabricatorPasteDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'paste';
  }

}
