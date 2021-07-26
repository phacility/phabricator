<?php

final class ManiphestCategoryCustomField extends ManiphestCustomField {

  private $value;

  public function getFieldKey() {
    return 'rivigo:custom-category';
  }

  public function getFieldName() {
    return 'Rivigo Category';
  }

  public function getFieldDescription() {
    return 'Rivigo Category';
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
      ->setOptions(ManiphestTaskCategory::getTaskCategoryMap());
  }

  public function renderPropertyViewLabel() {
    return pht('Rivigo Category');
  }

  public function renderPropertyViewValue(array $handles) {
    return pht($this->value);
  }

}
