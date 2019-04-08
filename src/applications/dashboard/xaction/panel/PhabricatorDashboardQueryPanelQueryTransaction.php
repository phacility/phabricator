<?php

final class PhabricatorDashboardQueryPanelQueryTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'search.query';

  protected function getPropertyKey() {
    return 'key';
  }

}
