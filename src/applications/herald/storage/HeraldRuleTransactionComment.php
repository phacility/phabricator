<?php

final class HeraldRuleTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new HeraldRuleTransaction();
  }

}
