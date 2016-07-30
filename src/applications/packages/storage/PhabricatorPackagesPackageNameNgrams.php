<?php

final class PhabricatorPackagesPackageNameNgrams
  extends PhabricatorPackagesNgrams {

  public function getNgramKey() {
    return 'packagename';
  }

  public function getColumnName() {
    return 'name';
  }

}
