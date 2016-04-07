<?php

final class PhabricatorBadgesBadgeNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'badgename';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'badges';
  }

}
