<?php

final class PhabricatorDashboardPanelNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'dashboardpanel';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'dashboard';
  }

}
