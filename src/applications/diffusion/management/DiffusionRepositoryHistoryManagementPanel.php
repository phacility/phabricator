<?php

final class DiffusionRepositoryHistoryManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'history';

  public function getManagementPanelLabel() {
    return pht('History');
  }

  public function getManagementPanelOrder() {
    return 900;
  }

  public function buildManagementPanelContent() {
    return $this->newTimeline();
  }


}
