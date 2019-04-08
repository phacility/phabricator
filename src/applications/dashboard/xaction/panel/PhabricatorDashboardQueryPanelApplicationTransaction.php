<?php

final class PhabricatorDashboardQueryPanelApplicationTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'query.application';

  protected function getPropertyKey() {
    return 'class';
  }

}
