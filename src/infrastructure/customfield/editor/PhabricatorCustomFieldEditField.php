<?php

final class PhabricatorCustomFieldEditField
  extends PhabricatorEditField {

  private $customField;
  private $httpParameterType;
  private $conduitParameterType;

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

  public function setCustomFieldConduitParameterType(
    ConduitParameterType $type) {
    $this->conduitParameterType = $type;
    return $this;
  }

  public function getCustomFieldConduitParameterType() {
    return $this->conduitParameterType;
  }

  protected function buildControl() {
    $field = $this->getCustomField();
    $clone = clone $field;

    $value = $this->getValue();
    $clone->setValueFromApplicationTransactions($value);

    return $clone->renderEditControl(array());
  }

  protected function newEditType() {
    $conduit_type = $this->newConduitParameterType();
    if (!$conduit_type) {
      return null;
    }

    $type = id(new PhabricatorCustomFieldEditType())
      ->setCustomField($this->getCustomField())
      ->setConduitParameterType($conduit_type);

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

  protected function newConduitEditTypes() {
    $field = $this->getCustomField();

    if (!$field->shouldAppearInConduitTransactions()) {
      return array();
    }

    return parent::newConduitEditTypes();
  }

  protected function newHTTPParameterType() {
    $type = $this->getCustomFieldHTTPParameterType();

    if ($type) {
      return clone $type;
    }

    return null;
  }

  protected function newConduitParameterType() {
    $type = $this->getCustomFieldConduitParameterType();

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
