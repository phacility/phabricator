<?php

final class AlmanacDeviceTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new AlmanacDeviceTransaction();
  }

}
