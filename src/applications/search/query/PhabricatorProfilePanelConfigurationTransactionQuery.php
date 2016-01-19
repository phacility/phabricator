<?php

final class PhabricatorProfilePanelConfigurationTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorProfilePanelConfigurationTransaction();
  }

}
