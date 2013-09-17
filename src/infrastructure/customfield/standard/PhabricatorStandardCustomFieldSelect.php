<?php

final class PhabricatorStandardCustomFieldSelect
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'select';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newStringIndex($value);
    }

    return $indexes;
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {
    return $request->getArr($this->getFieldKey());
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {
    if ($value) {
      $query->withApplicationSearchContainsConstraint(
        $this->newStringIndex(null),
        $value);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value,
    array $handles) {

    if (!is_array($value)) {
      $value = array();
    }
    $value = array_fuse($value);

    $control = id(new AphrontFormCheckboxControl())
      ->setLabel($this->getFieldName());

    foreach ($this->getOptions() as $name => $option) {
      $control->addCheckbox(
        $this->getFieldKey().'[]',
        $name,
        $option,
        isset($value[$name]));
    }

    $form->appendChild($control);
  }

  private function getOptions() {
    return $this->getFieldConfigValue('options', array());
  }

  public function renderEditControl() {
    return id(new AphrontFormSelectControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setOptions($this->getOptions());
  }

  public function renderPropertyViewValue() {
    return idx($this->getOptions(), $this->getFieldValue());
  }

}
