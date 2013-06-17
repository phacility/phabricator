<?php

final class PhabricatorAuthProviderConfigTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhabricatorAuthProviderConfigTransaction();
  }

}
