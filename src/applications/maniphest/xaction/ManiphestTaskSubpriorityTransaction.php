<?php

final class ManiphestTaskSubpriorityTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'subpriority';

  public function generateOldValue($object) {
    return $object->getSubpriority();
  }

  public function applyInternalEffects($object, $value) {
    $object->setSubpriority($value);
  }

  public function shouldHide() {
    return true;
  }


}
