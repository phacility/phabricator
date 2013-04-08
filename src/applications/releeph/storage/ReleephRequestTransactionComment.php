<?php

final class ReleephRequestTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new ReleephRequestTransaction();
  }

}
