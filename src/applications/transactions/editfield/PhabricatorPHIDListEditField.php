<?php

abstract class PhabricatorPHIDListEditField
  extends PhabricatorEditField {

  private $useEdgeTransactions;
  private $transactionDescriptions = array();

  public function setUseEdgeTransactions($use_edge_transactions) {
    $this->useEdgeTransactions = $use_edge_transactions;
    return $this;
  }

  public function getUseEdgeTransactions() {
    return $this->useEdgeTransactions;
  }

  public function setEdgeTransactionDescriptions($add, $rem, $set) {
    $this->transactionDescriptions = array(
      '+' => $add,
      '-' => $rem,
      '=' => $set,
    );
    return $this;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDListHTTPParameterType();
  }

  public function getValueForTransaction() {
    $new = parent::getValueForTransaction();

    if (!$this->getUseEdgeTransactions()) {
      return $new;
    }

    $old = $this->getInitialValue();
    if ($old === null) {
      return array(
        '=' => array_fuse($new),
      );
    }

    // If we're building an edge transaction and the request has data about the
    // original value the user saw when they loaded the form, interpret the
    // edit as a mixture of "+" and "-" operations instead of a single "="
    // operation. This limits our exposure to race conditions by making most
    // concurrent edits merge correctly.

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    $value = array();

    if ($add) {
      $value['+'] = array_fuse($add);
    }
    if ($rem) {
      $value['-'] = array_fuse($rem);
    }

    return $value;
  }

  protected function newEditType() {
    if ($this->getUseEdgeTransactions()) {
      return new PhabricatorEdgeEditType();
    }

    return parent::newEditType();
  }

  public function getConduitEditTypes() {
    if (!$this->getUseEdgeTransactions()) {
      return parent::getConduitEditTypes();
    }

    $transaction_type = $this->getTransactionType();
    if ($transaction_type === null) {
      return array();
    }

    $type_key = $this->getEditTypeKey();
    $strings = $this->transactionDescriptions;

    $base = $this->getEditType();

    $add = id(clone $base)
      ->setEditType($type_key.'.add')
      ->setEdgeOperation('+')
      ->setDescription(idx($strings, '+'))
      ->setValueDescription(pht('List of PHIDs to add.'));

    $rem = id(clone $base)
      ->setEditType($type_key.'.remove')
      ->setEdgeOperation('-')
      ->setDescription(idx($strings, '-'))
      ->setValueDescription(pht('List of PHIDs to remove.'));

    $set = id(clone $base)
      ->setEditType($type_key.'.set')
      ->setEdgeOperation('=')
      ->setDescription(idx($strings, '='))
      ->setValueDescription(pht('List of PHIDs to set.'));

    return array(
      $add,
      $rem,
      $set,
    );
  }

}
