<?php

abstract class PhabricatorTransactionChange extends Phobject {

  private $transaction;
  private $oldValue;
  private $newValue;

  final public function setTransaction(
    PhabricatorApplicationTransaction $xaction) {
    $this->transaction = $xaction;
    return $this;
  }

  final public function getTransaction() {
    return $this->transaction;
  }

  final public function setOldValue($old_value) {
    $this->oldValue = $old_value;
    return $this;
  }

  final public function getOldValue() {
    return $this->oldValue;
  }

  final public function setNewValue($new_value) {
    $this->newValue = $new_value;
    return $this;
  }

  final public function getNewValue() {
    return $this->newValue;
  }

}
