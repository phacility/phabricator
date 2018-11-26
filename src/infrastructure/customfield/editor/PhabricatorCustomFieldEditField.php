<?php

final class PhabricatorCustomFieldEditField
  extends PhabricatorEditField {

  private $customField;
  private $httpParameterType;
  private $conduitParameterType;
  private $bulkParameterType;
  private $commentAction;

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

  public function setCustomFieldBulkParameterType(
    BulkParameterType $type) {
    $this->bulkParameterType = $type;
    return $this;
  }

  public function getCustomFieldBulkParameterType() {
    return $this->bulkParameterType;
  }

  public function setCustomFieldCommentAction(
    PhabricatorEditEngineCommentAction $comment_action) {
    $this->commentAction = $comment_action;
    return $this;
  }

  public function getCustomFieldCommentAction() {
    return $this->commentAction;
  }

  protected function buildControl() {
    if (!$this->getIsFormField()) {
      return null;
    }

    $field = $this->getCustomField();
    $clone = clone $field;

    $value = $this->getValue();
    $clone->setValueFromApplicationTransactions($value);

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

  protected function getValueForCommentAction($value) {
    $field = $this->getCustomField();
    $clone = clone $field;
    $clone->setValueFromApplicationTransactions($value);

    // TODO: This is somewhat bogus because only StandardCustomFields
    // implement a getFieldValue() method -- not all CustomFields. Today,
    // only StandardCustomFields can ever actually generate a comment action
    // so we never reach this method with other field types.

    return $clone->getFieldValue();
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

  protected function newCommentAction() {
    $action = $this->getCustomFieldCommentAction();

    if ($action) {
      return clone $action;
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

  protected function newBulkParameterType() {
    $type = $this->getCustomFieldBulkParameterType();

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
