<?php

final class PhabricatorUserPreferencesTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorUserPreferencesTransaction();
  }

}
