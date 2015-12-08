<?php

abstract class PhabricatorPHIDListEditField
  extends PhabricatorEditField {

  private $useEdgeTransactions;
  private $transactionDescriptions = array();
  private $isSingleValue;

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

  public function setSingleValue($value) {
    if ($value === null) {
      $value = array();
    } else {
      $value = array($value);
    }

    $this->isSingleValue = true;
    return $this->setValue($value);
  }

  public function getIsSingleValue() {
    return $this->isSingleValue;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDListHTTPParameterType();
  }

  public function readValueFromComment($value) {
    // TODO: This is really hacky -- make sure we pass a plain PHID list to
    // the edit type. This method probably needs to move down to EditType, and
    // maybe more additional logic does too.
    $this->setUseEdgeTransactions(false);
    return parent::readValueFromComment($value);
  }

  public function getValueForTransaction() {
    $new = parent::getValueForTransaction();

    if ($this->getIsSingleValue()) {
      if ($new) {
        return head($new);
      } else {
        return null;
      }
    }

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

    $type = new PhabricatorDatasourceEditType();
    $type->setIsSingleValue($this->getIsSingleValue());
    return $type;
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
