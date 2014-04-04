<?php

final class PhabricatorAuthProviderConfigTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorAuthProviderConfigTransaction();
  }

}
