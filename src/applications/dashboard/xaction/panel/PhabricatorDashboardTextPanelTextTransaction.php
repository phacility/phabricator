<?php

final class PhabricatorDashboardTextPanelTextTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'text.text';

  protected function getPropertyKey() {
    return 'text';
  }

}
