<?php

abstract class PhabricatorTokenizerEditField
  extends PhabricatorPHIDListEditField {

  private $commentActionDefaultValue;

  abstract protected function newDatasource();

  public function setCommentActionDefaultValue(array $default) {
    $this->commentActionDefaultValue = $default;
    return $this;
  }

  public function getCommentActionDefaultValue() {
    return $this->commentActionDefaultValue;
  }

  protected function newControl() {
    $control = id(new AphrontFormTokenizerControl())
      ->setDatasource($this->newDatasource());

    $initial_value = $this->getInitialValue();
    if ($initial_value !== null) {
      $control->setOriginalValue($initial_value);
    }

    if ($this->getIsSingleValue()) {
      $control->setLimit(1);
    }

    return $control;
  }

  protected function getInitialValueFromSubmit(AphrontRequest $request, $key) {
    return $request->getArr($key.'.original');
  }

  protected function newEditType() {
    $type = parent::newEditType();

    $datasource = $this->newDatasource()
      ->setViewer($this->getViewer());
    $type->setDatasource($datasource);

    return $type;
  }

  public function getCommentEditTypes() {
    $label = $this->getCommentActionLabel();
    if ($label === null) {
      return array();
    }

    $transaction_type = $this->getTransactionType();
    if ($transaction_type === null) {
      return array();
    }

    if ($this->getUseEdgeTransactions()) {
      $type_key = $this->getEditTypeKey();
      $base = $this->getEditType();

      $add = id(clone $base)
        ->setEditType($type_key.'.add')
        ->setEdgeOperation('+')
        ->setLabel($label);

      return array($add);
    }

    $edit = $this->getEditType()
      ->setLabel($label);

    $default = $this->getCommentActionDefaultValue();
    if ($default) {
      $edit->setDefaultValue($default);
    }

    return array($edit);
  }

}
