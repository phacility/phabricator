<?php

final class PhortunePaymentProviderConfigTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortunePaymentProviderConfigTransaction();
  }

}
