<?php

final class ManiphestTaskSubpriorityTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'subpriority';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    // This transaction is obsolete, but we're keeping the class around so it
    // is hidden from timelines until we destroy the actual transaction data.
    throw new PhutilMethodNotImplementedException();
  }

  public function shouldHide() {
    return true;
  }

}
