<?php

final class PhabricatorEditEngineConfigurationTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorEditEngineConfigurationTransaction();
  }

}
