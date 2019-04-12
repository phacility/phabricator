<?php

final class PhabricatorDashboardFullLayoutMode
  extends PhabricatorDashboardLayoutMode {

  const LAYOUTMODE = 'layout-mode-full';

  public function getLayoutModeOrder() {
    return 0;
  }

  public function getLayoutModeName() {
    return pht('One Column: 100%%');
  }

  public function getLayoutModeColumns() {
    return array(
      $this->newColumn()
        ->setColumnKey('main'),
    );
  }

}
