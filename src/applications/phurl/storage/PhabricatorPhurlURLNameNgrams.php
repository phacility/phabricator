<?php

final class PhabricatorPhurlURLNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'phurlname';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'phurl';
  }

}
