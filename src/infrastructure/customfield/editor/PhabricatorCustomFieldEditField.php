<?php

final class PhabricatorCustomFieldEditField
  extends PhabricatorEditField {

  private $customField;

  public function setCustomField(PhabricatorCustomField $custom_field) {
    $this->customField = $custom_field;
    return $this;
  }

  public function getCustomField() {
    return $this->customField;
  }

  protected function buildControl() {
    $field = $this->getCustomField();
    $clone = clone $field;

    if ($this->getIsSubmittedForm()) {
      $value = $this->getValue();
      $clone->setValueFromApplicationTransactions($value);
    }

    return $clone->renderEditControl(array());
  }

  protected function newEditType() {
    return id(new PhabricatorCustomFieldEditType())
      ->setCustomField($this->getCustomField());
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

  protected function getValueExistsInRequest(AphrontRequest $request, $key) {
    // For now, never read these out of the request.
    return false;
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
    // TODO: For now, don't support custom fields over Conduit.
    return array();
  }

  protected function newHTTPParameterType() {
    // TODO: For now, don't support custom fields for HTTP prefill.
    return null;
  }

}
