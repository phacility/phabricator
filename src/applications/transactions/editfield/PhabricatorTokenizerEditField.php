<?php

abstract class PhabricatorTokenizerEditField
  extends PhabricatorPHIDListEditField {

  private $commentActionLabel;

  abstract protected function newDatasource();

  public function setCommentActionLabel($label) {
    $this->commentActionLabel = $label;
    return $this;
  }

  public function getCommentActionLabel() {
    return $this->commentActionLabel;
  }

  protected function newControl() {
    $control = id(new AphrontFormTokenizerControl())
      ->setDatasource($this->newDatasource());

    $initial_value = $this->getInitialValue();
    if ($initial_value !== null) {
      $control->setOriginalValue($initial_value);
    }

    return $control;
  }

  protected function getInitialValueFromSubmit(AphrontRequest $request, $key) {
    return $request->getArr($key.'.original');
  }

  protected function newEditType() {
    $type = parent::newEditType();

    if ($this->getUseEdgeTransactions()) {
      $datasource = $this->newDatasource()
        ->setViewer($this->getViewer());
      $type->setDatasource($datasource);
    }

    return $type;
  }

  public function getCommentEditTypes() {
    if (!$this->getUseEdgeTransactions()) {
      return parent::getCommentEditTypes();
    }

    $transaction_type = $this->getTransactionType();
    if ($transaction_type === null) {
      return array();
    }

    $label = $this->getCommentActionLabel();
    if ($label === null) {
      return array();
    }

    $type_key = $this->getEditTypeKey();
    $base = $this->getEditType();

    $add = id(clone $base)
      ->setEditType($type_key.'.add')
      ->setEdgeOperation('+')
      ->setLabel($label);

    return array($add);
  }

}
