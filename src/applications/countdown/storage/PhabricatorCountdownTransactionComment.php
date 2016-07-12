<?php

final class PhabricatorCountdownTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorCountdownTransaction();
  }

}
