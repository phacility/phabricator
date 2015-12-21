<?php

final class PhabricatorOwnersPackageNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'name';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'owners';
  }

}
