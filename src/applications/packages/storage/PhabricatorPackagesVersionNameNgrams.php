<?php

final class PhabricatorPackagesVersionNameNgrams
  extends PhabricatorPackagesNgrams {

  public function getNgramKey() {
    return 'versionname';
  }

  public function getColumnName() {
    return 'name';
  }

}
