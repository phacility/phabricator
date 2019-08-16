<?php

final class PhortunePaymentMethodTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortunePaymentMethodTransaction();
  }

}
