<?php

final class FundInitiativeTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new FundInitiativeTransaction();
  }

}
