<?php

final class PhabricatorSlowvoteTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorSlowvoteTransaction();
  }

}
