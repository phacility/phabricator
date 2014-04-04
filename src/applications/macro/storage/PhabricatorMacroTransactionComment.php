<?php

final class PhabricatorMacroTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorMacroTransaction();
  }

}
