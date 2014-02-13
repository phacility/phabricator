<?php

final class PhabricatorDashboardPanelTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorDashboardPanelTransaction();
  }

}
