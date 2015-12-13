<?php

final class PhabricatorSearchCustomFieldProxyField
  extends PhabricatorSearchField {

  private $searchEngine;
  private $customField;

  public function setSearchEngine(PhabricatorApplicationSearchEngine $engine) {
    $this->searchEngine = $engine;
    return $this;
  }

  public function getSearchEngine() {
    return $this->searchEngine;
  }

  public function setCustomField(PhabricatorCustomField $field) {
    $this->customField = $field;
    $this->setKey('custom:'.$field->getFieldIndex());

    $aliases = array();
    $aliases[] = $field->getFieldKey();
    $this->setAliases($aliases);

    return $this;
  }

  public function getLabel() {
    return $this->getCustomField()->getFieldName();
  }

  public function getCustomField() {
    return $this->customField;
  }

  protected function getDefaultValue() {
    return null;
  }

  public function getConduitKey() {
    return $this->getCustomField()->getModernFieldKey();
  }

  protected function getValueExistsInRequest(AphrontRequest $request, $key) {
    // TODO: For historical reasons, the keys we look for don't line up with
    // the keys that CustomFields use. Just skip the check for existence and
    // always read the value. It would be vaguely nice to make rendering more
    // consistent instead.
    return true;
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getCustomField()->readApplicationSearchValueFromRequest(
      $this->getSearchEngine(),
      $request);
  }

  public function appendToForm(AphrontFormView $form) {
    return $this->getCustomField()->appendToApplicationSearchForm(
      $this->getSearchEngine(),
      $form,
      $this->getValue());
  }

  public function getDescription() {
    return $this->getCustomField()->getFieldDescription();
  }

  protected function newConduitParameterType() {
    return $this->getCustomField()->getConduitSearchParameterType();
  }

}
