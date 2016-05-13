<?php

final class DiffusionRepositoryHistoryManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'history';

  public function getManagementPanelLabel() {
    return pht('History');
  }

  public function getManagementPanelOrder() {
    return 2000;
  }

  public function getManagementPanelIcon() {
    return 'fa-list-ul';
  }

  public function buildManagementPanelContent() {
    return $this->newTimeline();
  }


}
