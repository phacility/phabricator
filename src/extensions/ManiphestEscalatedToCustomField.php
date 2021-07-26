<?php

final class ManiphestEscalatedToCustomField extends ManiphestCustomField {

  private $value;

  public function getFieldKey() {
    return 'rivigo:escalated-to';
  }

  public function getFieldName() {
    return 'Escalated To';
  }

  public function getFieldDescription() {
    return 'Escalated To';
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function shouldAppearInEditEngine() {
    return true;
  }

  public function shouldUseStorage() {
    return true;
  }

  public function getValueForStorage() {
    return $this->value;
  }

  public function setValueFromStorage($value) {
    $this->value = $value;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getFieldKey());
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormSelectControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setValue($this->value)
      ->setOptions(ManiphestTaskEscalation::getTaskEscalationMap());
  }

  public function renderPropertyViewLabel() {
    return pht('Escalated To');
  }

  public function renderPropertyViewValue(array $handles) {
    return pht($this->value);
  }

}
