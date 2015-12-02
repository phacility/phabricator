<?php

final class PhabricatorCustomFieldEditField
  extends PhabricatorEditField {

  private $customField;
  private $httpParameterType;

  public function setCustomField(PhabricatorCustomField $custom_field) {
    $this->customField = $custom_field;
    return $this;
  }

  public function getCustomField() {
    return $this->customField;
  }

  public function setCustomFieldHTTPParameterType(
    AphrontHTTPParameterType $type) {
    $this->httpParameterType = $type;
    return $this;
  }

  public function getCustomFieldHTTPParameterType() {
    return $this->httpParameterType;
  }

  protected function buildControl() {
    $field = $this->getCustomField();
    $clone = clone $field;

    $value = $this->getValue();
    $clone->setValueFromApplicationTransactions($value);

    return $clone->renderEditControl(array());
  }

  protected function newEditType() {
    $type = id(new PhabricatorCustomFieldEditType())
      ->setCustomField($this->getCustomField());

    $http_type = $this->getHTTPParameterType();
    if ($http_type) {
      $type->setValueType($http_type->getTypeName());
    }

    return $type;
  }

  public function getValueForTransaction() {
    $value = $this->getValue();
    $field = $this->getCustomField();

    // Avoid changing the value of the field itself, since later calls would
    // incorrectly reflect the new value.
    $clone = clone $field;
    $clone->setValueFromApplicationTransactions($value);
    return $clone->getNewValueForApplicationTransactions();
  }

  protected function getValueExistsInSubmit(AphrontRequest $request, $key) {
    return true;
  }

  protected function getValueFromSubmit(AphrontRequest $request, $key) {
    $field = $this->getCustomField();

    $clone = clone $field;

    $clone->readValueFromRequest($request);
    return $clone->getNewValueForApplicationTransactions();
  }

  public function getConduitEditTypes() {
    $field = $this->getCustomField();

    if (!$field->shouldAppearInConduitTransactions()) {
      return array();
    }

    return parent::getConduitEditTypes();
  }

  protected function newHTTPParameterType() {
    $type = $this->getCustomFieldHTTPParameterType();

    if ($type) {
      return clone $type;
    }

    return null;
  }

  public function getAllReadValueFromRequestKeys() {
    $keys = array();

    // NOTE: This piece of complexity is so we can expose a reasonable key in
    // the UI ("custom.x") instead of a crufty internal key ("std:app:x").
    // Perhaps we can simplify this some day.

    // In the parent, this is just getKey(), but that returns a cumbersome
    // key in EditFields. Use the simpler edit type key instead.
    $keys[] = $this->getEditTypeKey();

    foreach ($this->getAliases() as $alias) {
      $keys[] = $alias;
    }

    return $keys;
  }

}
