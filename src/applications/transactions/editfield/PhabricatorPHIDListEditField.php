<?php

abstract class PhabricatorPHIDListEditField
  extends PhabricatorEditField {

  private $useEdgeTransactions;
  private $isSingleValue;

  public function setUseEdgeTransactions($use_edge_transactions) {
    $this->useEdgeTransactions = $use_edge_transactions;
    return $this;
  }

  public function getUseEdgeTransactions() {
    return $this->useEdgeTransactions;
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

  protected function newConduitParameterType() {
    if ($this->getIsSingleValue()) {
      return new ConduitPHIDParameterType();
    } else {
      return new ConduitPHIDListParameterType();
    }
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $value = parent::getValueFromRequest($request, $key);
    if ($this->getIsSingleValue()) {
      $value = array_slice($value, 0, 1);
    }
    return $value;
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
    $type->setConduitParameterType($this->newConduitParameterType());
    return $type;
  }

  protected function newConduitEditTypes() {
    if (!$this->getUseEdgeTransactions()) {
      return parent::newConduitEditTypes();
    }

    $transaction_type = $this->getTransactionType();
    if ($transaction_type === null) {
      return array();
    }

    $type_key = $this->getEditTypeKey();

    $base = $this->getEditType();

    $add = id(clone $base)
      ->setEditType($type_key.'.add')
      ->setEdgeOperation('+')
      ->setConduitTypeDescription(pht('List of PHIDs to add.'))
      ->setConduitParameterType($this->getConduitParameterType());

    $rem = id(clone $base)
      ->setEditType($type_key.'.remove')
      ->setEdgeOperation('-')
      ->setConduitTypeDescription(pht('List of PHIDs to remove.'))
      ->setConduitParameterType($this->getConduitParameterType());

    $set = id(clone $base)
      ->setEditType($type_key.'.set')
      ->setEdgeOperation('=')
      ->setConduitTypeDescription(pht('List of PHIDs to set.'))
      ->setConduitParameterType($this->getConduitParameterType());

    return array(
      $add,
      $rem,
      $set,
    );
  }

}
