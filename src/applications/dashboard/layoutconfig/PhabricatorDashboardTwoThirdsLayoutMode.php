<?php

final class PhabricatorDashboardTwoThirdsLayoutMode
  extends PhabricatorDashboardLayoutMode {

  const LAYOUTMODE = 'layout-mode-thirds-and-third';

  public function getLayoutModeOrder() {
    return 600;
  }

  public function getLayoutModeName() {
    return pht('Two Columns: 66%%/33%%');
  }

  public function getLayoutModeColumns() {
    return array(
      $this->newColumn()
        ->setColumnKey('left')
        ->addClass('thirds'),
      $this->newColumn()
        ->setColumnKey('right')
        ->addClass('third'),
    );
  }

}
