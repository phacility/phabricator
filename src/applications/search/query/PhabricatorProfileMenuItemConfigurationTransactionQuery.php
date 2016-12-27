<?php

final class PhabricatorProfileMenuItemConfigurationTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorProfileMenuItemConfigurationTransaction();
  }

}
