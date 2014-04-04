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
        ->setRawStandardFieldKey($key)
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
          $this->setRequired($value);
          $this->setFieldError(true);
          break;
        case 'default':
          $this->setFieldValue($value);
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

  public function getInstructionsForEdit() {
    return $this->getFieldConfigValue('instructions');
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setName($this->getFieldKey())
      ->setCaption($this->getCaption())
      ->setValue($this->getFieldValue())
      ->setError($this->getFieldError())
      ->setLabel($this->getFieldName());
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
    PhabricatorApplicationTransaction $xaction,
    PhabricatorFeedStory $story) {

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


}
