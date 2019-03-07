<?php

final class ManiphestTaskUnlockEngine
  extends PhabricatorUnlockEngine {

  public function newUnlockOwnerTransactions($object, $user) {
    return array(
      $this->newTransaction($object)
        ->setTransactionType(ManiphestTaskOwnerTransaction::TRANSACTIONTYPE)
        ->setNewValue($user->getPHID()),
    );
  }

}
