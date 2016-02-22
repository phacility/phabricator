<?php

final class AlmanacNetworkNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'networkname';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'almanac';
  }

}
