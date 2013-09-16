<?php

final class PhabricatorStandardCustomField
  extends PhabricatorCustomField {

  private $fieldKey;
  private $fieldName;
  private $fieldType;
  private $fieldValue;
  private $fieldDescription;
  private $fieldConfig;
  private $applicationField;

  public static function buildStandardFields(
    PhabricatorCustomField $template,
    array $config) {

    $fields = array();
    foreach ($config as $key => $value) {
      $namespace = $template->getStandardCustomFieldNamespace();
      $full_key = "std:{$namespace}:{$key}";

      $template = clone $template;
      $standard = id(new PhabricatorStandardCustomField($full_key))
        ->setFieldConfig($value)
        ->setApplicationField($template);

      $field = $template->setProxy($standard);
      $fields[] = $field;
    }

    return $fields;
  }

  public function __construct($key) {
    $this->fieldKey = $key;
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

  public function setFieldType($type) {
    $this->fieldType = $type;
    return $this;
  }

  public function getFieldType() {
    return $this->fieldType;
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
    $this->setFieldType('text');

    foreach ($config as $key => $value) {
      switch ($key) {
        case 'name':
          $this->setFieldName($value);
          break;
        case 'type':
          $this->setFieldType($value);
          break;
        case 'description':
          $this->setFieldDescription($value);
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


  public function getFieldKey() {
    return $this->fieldKey;
  }

  public function getFieldName() {
    return coalesce($this->fieldName, parent::getFieldName());
  }

  public function getFieldDescription() {
    return coalesce($this->fieldDescription, parent::getFieldDescription());
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
    $this->setFieldValue($request->getStr($this->getFieldKey()));
  }

  public function renderEditControl() {
    $type = $this->getFieldType();
    switch ($type) {
      case 'text':
      default:
        return id(new AphrontFormTextControl())
          ->setName($this->getFieldKey())
          ->setValue($this->getFieldValue())
          ->setLabel($this->getFieldName());
    }
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
    $type = $this->getFieldType();
    switch ($type) {
      case 'text':
      default:
        return array(
          $this->newStringIndex($this->getFieldValue()),
        );
    }
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {
    $type = $this->getFieldType();
    switch ($type) {
      case 'text':
      default:
        return $request->getStr('std:'.$this->getFieldIndex());
    }
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {
    $type = $this->getFieldType();
    switch ($type) {
      case 'text':
      default:
        if (strlen($value)) {
          $query->withApplicationSearchContainsConstraint(
            $this->newStringIndex(null),
            $value);
        }
        break;
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value,
    array $handles) {

    $type = $this->getFieldType();
    switch ($type) {
      case 'text':
      default:
        $form->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel($this->getFieldName())
            ->setName('std:'.$this->getFieldIndex())
            ->setValue($value));
        break;
    }

  }

}
