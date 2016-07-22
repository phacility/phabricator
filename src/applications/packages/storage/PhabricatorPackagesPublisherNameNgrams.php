<?php

final class PhabricatorPackagesPublisherNameNgrams
  extends PhabricatorPackagesNgrams {

  public function getNgramKey() {
    return 'publishername';
  }

  public function getColumnName() {
    return 'name';
  }

}
