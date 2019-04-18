<?php

final class PhabricatorDashboardHalfLayoutMode
  extends PhabricatorDashboardLayoutMode {

  const LAYOUTMODE = 'layout-mode-half-and-half';

  public function getLayoutModeOrder() {
    return 500;
  }

  public function getLayoutModeName() {
    return pht('Two Columns: 50%%/50%%');
  }

  public function getLayoutModeColumns() {
    return array(
      $this->newColumn()
        ->setColumnKey('left')
        ->addClass('half'),
      $this->newColumn()
        ->setColumnKey('right')
        ->addClass('half'),
    );
  }

}
