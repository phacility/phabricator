<?php

final class PhabricatorDashboardOneThirdLayoutMode
  extends PhabricatorDashboardLayoutMode {

  const LAYOUTMODE = 'layout-mode-third-and-thirds';

  public function getLayoutModeOrder() {
    return 700;
  }

  public function getLayoutModeName() {
    return pht('Two Columns: 33%%/66%%');
  }

  public function getLayoutModeColumns() {
    return array(
      $this->newColumn()
        ->setColumnKey('left')
        ->addClass('third'),
      $this->newColumn()
        ->setColumnKey('right')
        ->addClass('thirds'),
    );
  }

}
