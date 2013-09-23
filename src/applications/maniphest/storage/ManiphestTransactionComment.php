<?php

final class ManiphestTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new ManiphestTransactionPro();
  }

}
