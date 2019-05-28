<?php

final class PhabricatorDashboardChartPanelChartTransaction
  extends PhabricatorDashboardPanelPropertyTransaction {

  const TRANSACTIONTYPE = 'chart.chartKey';

  protected function getPropertyKey() {
    return 'chartKey';
  }

}
