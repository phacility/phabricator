<?php

final class AlmanacServiceNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'servicename';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'almanac';
  }

}
