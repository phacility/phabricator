<?php

abstract class PhabricatorStandardCustomField
  extends PhabricatorCustomField {

  private $fieldKey;
  private $fieldName;
  private $fieldValue;
  private $fieldDescription;
  private $fieldConfig;
  private $applicationField;
  private $strings;

  abstract public function getFieldType();

  public static function buildStandardFields(
    PhabricatorCustomField $template,
    array $config) {

    $types = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->loadObjects();
    $types = mpull($types, null, 'getFieldType');

    $fields = array();
    foreach ($config as $key => $value) {
      $type = idx($value, 'type', 'text');
      if (empty($types[$type])) {
        // TODO: We should have better typechecking somewhere, and then make
        // this more serious.
        continue;
      }

      $namespace = $template->getStandardCustomFieldNamespace();
      $full_key = "std:{$namespace}:{$key}";

      $template = clone $template;
      $standard = id(clone $types[$type])
        ->setFieldKey($full_key)
        ->setFieldConfig($value)
        ->setApplicationField($template);

      $field = $template->setProxy($standard);
      $fields[] = $field;
    }

    return $fields;
  }

  public function setApplicationField(
    PhabricatorStandardCustomFieldInterface $application_field) {
    $this->applicationField = $application_field;
    return $this;
  }

  public function getApplicationField() {
    return $this->applicationField;
  }

  public function setFieldName($name) {
    $this->fieldName = $name;
    return $this;
  }

  public function getFieldValue() {
    return $this->fieldValue;
  }

  public function setFieldValue($value) {
    $this->fieldValue = $value;
    return $this;
  }

  public function setFieldDescription($description) {
    $this->fieldDescription = $description;
    return $this;
  }

  public function setFieldConfig(array $config) {
    foreach ($config as $key => $value) {
      switch ($key) {
        case 'name':
          $this->setFieldName($value);
          break;
        case 'description':
          $this->setFieldDescription($value);
          break;
        case 'strings':
          $this->setStrings($value);
          break;
        case 'type':
          // We set this earlier on.
          break;
      }
    }
    $this->fieldConfig = $config;
    return $this;
  }

  public function getFieldConfigValue($key, $default = null) {
    return idx($this->fieldConfig, $key, $default);
  }



/* -(  PhabricatorCustomField  )--------------------------------------------- */


  public function setFieldKey($field_key) {
    $this->fieldKey = $field_key;
    return $this;
  }

  public function getFieldKey() {
    return $this->fieldKey;
  }

  public function getFieldName() {
    return coalesce($this->fieldName, parent::getFieldName());
  }

  public function getFieldDescription() {
    return coalesce($this->fieldDescription, parent::getFieldDescription());
  }

  public function setStrings(array $strings) {
    $this->strings = $strings;
    return;
  }

  public function getString($key, $default = null) {
    return idx($this->strings, $key, $default);
  }

  public function shouldUseStorage() {
    return true;
  }

  public function getValueForStorage() {
    return $this->getFieldValue();
  }

  public function setValueFromStorage($value) {
    return $this->setFieldValue($value);
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function shouldAppearInEditView() {
    return $this->getFieldConfigValue('edit', true);
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $value = $request->getStr($this->getFieldKey());
    if (!strlen($value)) {
      $value = null;
    }
    $this->setFieldValue($value);
  }

  public function renderEditControl() {
    return id(new AphrontFormTextControl())
      ->setName($this->getFieldKey())
      ->setValue($this->getFieldValue())
      ->setLabel($this->getFieldName());
  }

  public function newStorageObject() {
    return $this->getApplicationField()->newStorageObject();
  }

  public function shouldAppearInPropertyView() {
    return $this->getFieldConfigValue('view', true);
  }

  public function renderPropertyViewValue() {
    return $this->getFieldValue();
  }

  public function shouldAppearInApplicationSearch() {
    return $this->getFieldConfigValue('search', false);
  }

  protected function newStringIndexStorage() {
    return $this->getApplicationField()->newStringIndexStorage();
  }

  protected function newNumericIndexStorage() {
    return $this->getApplicationField()->newNumericIndexStorage();
  }

  public function buildFieldIndexes() {
    return array();
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {
    return;
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {
    return;
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value,
    array $handles) {
    return;
  }

}
