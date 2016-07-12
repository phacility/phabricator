<?php

final class PhabricatorPhurlURLTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorPhurlURLTransaction();
  }

}
