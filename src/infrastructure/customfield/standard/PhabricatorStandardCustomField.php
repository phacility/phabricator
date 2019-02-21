<?php

abstract class PhabricatorStandardCustomField
  extends PhabricatorCustomField {

  private $rawKey;
  private $fieldKey;
  private $fieldName;
  private $fieldValue;
  private $fieldDescription;
  private $fieldConfig;
  private $applicationField;
  private $strings = array();
  private $caption;
  private $fieldError;
  private $required;
  private $default;
  private $isCopyable;
  private $hasStorageValue;
  private $isBuiltin;
  private $isEnabled = true;

  abstract public function getFieldType();

  public static function buildStandardFields(
    PhabricatorCustomField $template,
    array $config,
    $builtin = false) {

    $types = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFieldType')
      ->execute();

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
        ->setRawStandardFieldKey($key)
        ->setFieldKey($full_key)
        ->setFieldConfig($value)
        ->setApplicationField($template);

      if ($builtin) {
        $standard->setIsBuiltin(true);
      }

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

  public function setCaption($caption) {
    $this->caption = $caption;
    return $this;
  }

  public function getCaption() {
    return $this->caption;
  }

  public function setFieldDescription($description) {
    $this->fieldDescription = $description;
    return $this;
  }

  public function setIsBuiltin($is_builtin) {
    $this->isBuiltin = $is_builtin;
    return $this;
  }

  public function getIsBuiltin() {
    return $this->isBuiltin;
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
        case 'caption':
          $this->setCaption($value);
          break;
        case 'required':
          if ($value) {
            $this->setRequired($value);
            $this->setFieldError(true);
          }
          break;
        case 'default':
          $this->setFieldValue($value);
          break;
        case 'copy':
          $this->setIsCopyable($value);
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

  public function setFieldError($field_error) {
    $this->fieldError = $field_error;
    return $this;
  }

  public function getFieldError() {
    return $this->fieldError;
  }

  public function setRequired($required) {
    $this->required = $required;
    return $this;
  }

  public function getRequired() {
    return $this->required;
  }

  public function setRawStandardFieldKey($raw_key) {
    $this->rawKey = $raw_key;
    return $this;
  }

  public function getRawStandardFieldKey() {
    return $this->rawKey;
  }

  public function setIsEnabled($is_enabled) {
    $this->isEnabled = $is_enabled;
    return $this;
  }

  public function getIsEnabled() {
    return $this->isEnabled;
  }

  public function isFieldEnabled() {
    return $this->getIsEnabled();
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

  public function setIsCopyable($is_copyable) {
    $this->isCopyable = $is_copyable;
    return $this;
  }

  public function getIsCopyable() {
    return $this->isCopyable;
  }

  public function shouldUseStorage() {
    try {
      $object = $this->newStorageObject();
      return true;
    } catch (PhabricatorCustomFieldImplementationIncompleteException $ex) {
      return false;
    }
  }

  public function getValueForStorage() {
    return $this->getFieldValue();
  }

  public function setValueFromStorage($value) {
    return $this->setFieldValue($value);
  }

  public function didSetValueFromStorage() {
    $this->hasStorageValue = true;
    return $this;
  }

  public function getOldValueForApplicationTransactions() {
    if ($this->hasStorageValue) {
      return $this->getValueForStorage();
    } else {
      return null;
    }
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

  public function getInstructionsForEdit() {
    return $this->getFieldConfigValue('instructions');
  }

  public function getPlaceholder() {
    return $this->getFieldConfigValue('placeholder', null);
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setName($this->getFieldKey())
      ->setCaption($this->getCaption())
      ->setValue($this->getFieldValue())
      ->setError($this->getFieldError())
      ->setLabel($this->getFieldName())
      ->setPlaceholder($this->getPlaceholder());
  }

  public function newStorageObject() {
    return $this->getApplicationField()->newStorageObject();
  }

  public function shouldAppearInPropertyView() {
    return $this->getFieldConfigValue('view', true);
  }

  public function renderPropertyViewValue(array $handles) {
    if (!strlen($this->getFieldValue())) {
      return null;
    }
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

  public function buildOrderIndex() {
    return null;
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
    $value) {
    return;
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $this->setFieldError(null);

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    if ($this->getRequired()) {
      $value = $this->getOldValueForApplicationTransactions();

      $transaction = null;
      foreach ($xactions as $xaction) {
        $value = $xaction->getNewValue();
        if (!$this->isValueEmpty($value)) {
          $transaction = $xaction;
          break;
        }
      }
      if ($this->isValueEmpty($value)) {
        $error = new PhabricatorApplicationTransactionValidationError(
          $type,
          pht('Required'),
          pht('%s is required.', $this->getFieldName()),
          $transaction);
        $error->setIsMissingFieldError(true);
        $errors[] = $error;
        $this->setFieldError(pht('Required'));
      }
    }

    return $errors;
  }

  protected function isValueEmpty($value) {
    if (is_array($value)) {
      return empty($value);
    }
    return !strlen($value);
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if (!$old) {
      return pht(
        '%s set %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $new);
    } else if (!$new) {
      return pht(
        '%s removed %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName());
    } else {
      return pht(
        '%s changed %s from %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $old,
        $new);
    }
  }

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $object_phid = $xaction->getObjectPHID();

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if (!$old) {
      return pht(
        '%s set %s to %s on %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $new,
        $xaction->renderHandleLink($object_phid));
    } else if (!$new) {
      return pht(
        '%s removed %s on %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($object_phid));
    } else {
      return pht(
        '%s changed %s from %s to %s on %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $old,
        $new,
        $xaction->renderHandleLink($object_phid));
    }
  }

  public function getHeraldFieldValue() {
    return $this->getFieldValue();
  }

  public function getFieldControlID($key = null) {
    $key = coalesce($key, $this->getRawStandardFieldKey());
    return 'std:control:'.$key;
  }

  public function shouldAppearInGlobalSearch() {
    return $this->getFieldConfigValue('fulltext', false);
  }

  public function updateAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {

    $field_key = $this->getFieldConfigValue('fulltext');

    // If the caller or configuration didn't specify a valid field key,
    // generate one automatically from the field index.
    if (!is_string($field_key) || (strlen($field_key) != 4)) {
      $field_key = '!'.substr($this->getFieldIndex(), 0, 3);
    }

    $field_value = $this->getFieldValue();
    if (strlen($field_value)) {
      $document->addField($field_key, $field_value);
    }
  }

  protected function newStandardEditField() {
    $short = $this->getModernFieldKey();

    return parent::newStandardEditField()
      ->setEditTypeKey($short)
      ->setIsCopyable($this->getIsCopyable());
  }

  public function shouldAppearInConduitTransactions() {
    return true;
  }

  public function shouldAppearInConduitDictionary() {
    return true;
  }

  public function getModernFieldKey() {
    if ($this->getIsBuiltin()) {
      return $this->getRawStandardFieldKey();
    } else {
      return 'custom.'.$this->getRawStandardFieldKey();
    }
  }

  public function getConduitDictionaryValue() {
    return $this->getFieldValue();
  }

  public function newExportData() {
    return $this->getFieldValue();
  }

}
