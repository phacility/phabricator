<?php

final class PhabricatorDashboardTabsPanelTabsTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'tabs.tabs';

  protected function getPropertyKey() {
    return 'config';
  }

}
