<?php

final class PhortuneSubscriptionTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhortuneSubscriptionTransaction();
  }

}
