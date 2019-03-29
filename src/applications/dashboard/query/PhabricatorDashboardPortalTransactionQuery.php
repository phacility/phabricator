<?php

final class PhabricatorDashboardPortalTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorDashboardPortalTransaction();
  }

}
