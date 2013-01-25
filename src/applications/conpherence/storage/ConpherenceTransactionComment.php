<?php

/**
 * @group conpherence
 */
final class ConpherenceTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $conpherencePHID;

  public function getApplicationTransactionObject() {
    return new ConpherenceTransaction();
  }

}
