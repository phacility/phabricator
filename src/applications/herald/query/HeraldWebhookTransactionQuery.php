<?php

final class HeraldWebhookTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new HeraldWebhookTransaction();
  }

}
