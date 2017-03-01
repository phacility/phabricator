<?php

final class PhabricatorDashboardNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'dashboard';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'dashboard';
  }

}
