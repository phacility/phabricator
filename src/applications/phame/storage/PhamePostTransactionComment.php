<?php

final class PhamePostTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhamePostTransaction();
  }

}
