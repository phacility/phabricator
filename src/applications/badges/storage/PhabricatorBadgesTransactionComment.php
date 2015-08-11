<?php

final class PhabricatorBadgesTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorBadgesTransaction();
  }

}
