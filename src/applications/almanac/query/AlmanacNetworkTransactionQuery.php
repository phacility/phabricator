<?php

final class AlmanacNetworkTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new AlmanacNetworkTransaction();
  }

}
