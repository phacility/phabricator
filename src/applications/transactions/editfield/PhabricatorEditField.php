<?php

abstract class PhabricatorEditField extends Phobject {

  private $key;
  private $viewer;
  private $label;
  private $aliases = array();
  private $value;
  private $initialValue;
  private $hasValue = false;
  private $object;
  private $transactionType;
  private $metadata = array();
  private $description;
  private $editTypeKey;
  private $isRequired;

  private $isLocked;
  private $isHidden;

  private $isPreview;
  private $isEditDefaults;
  private $isSubmittedForm;
  private $controlError;

  private $isReorderable = true;
  private $isDefaultable = true;
  private $isLockable = true;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setAliases(array $aliases) {
    $this->aliases = $aliases;
    return $this;
  }

  public function getAliases() {
    return $this->aliases;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setIsLocked($is_locked) {
    $this->isLocked = $is_locked;
    return $this;
  }

  public function getIsLocked() {
    return $this->isLocked;
  }

  public function setIsPreview($preview) {
    $this->isPreview = $preview;
    return $this;
  }

  public function getIsPreview() {
    return $this->isPreview;
  }

  public function setIsReorderable($is_reorderable) {
    $this->isReorderable = $is_reorderable;
    return $this;
  }

  public function getIsReorderable() {
    return $this->isReorderable;
  }

  public function setIsEditDefaults($is_edit_defaults) {
    $this->isEditDefaults = $is_edit_defaults;
    return $this;
  }

  public function getIsEditDefaults() {
    return $this->isEditDefaults;
  }

  public function setIsDefaultable($is_defaultable) {
    $this->isDefaultable = $is_defaultable;
    return $this;
  }

  public function getIsDefaultable() {
    return $this->isDefaultable;
  }

  public function setIsLockable($is_lockable) {
    $this->isLockable = $is_lockable;
    return $this;
  }

  public function getIsLockable() {
    return $this->isLockable;
  }

  public function setIsHidden($is_hidden) {
    $this->isHidden = $is_hidden;
    return $this;
  }

  public function getIsHidden() {
    return $this->isHidden;
  }

  public function setIsSubmittedForm($is_submitted) {
    $this->isSubmittedForm = $is_submitted;
    return $this;
  }

  public function getIsSubmittedForm() {
    return $this->isSubmittedForm;
  }

  public function setIsRequired($is_required) {
    $this->isRequired = $is_required;
    return $this;
  }

  public function getIsRequired() {
    return $this->isRequired;
  }

  public function setControlError($control_error) {
    $this->controlError = $control_error;
    return $this;
  }

  public function getControlError() {
    return $this->controlError;
  }

  protected function newControl() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function buildControl() {
    $control = $this->newControl();
    if ($control === null) {
      return null;
    }

    $control
      ->setValue($this->getValueForControl())
      ->setName($this->getKey());

    if (!$control->getLabel()) {
      $control->setLabel($this->getLabel());
    }

    if ($this->getIsSubmittedForm()) {
      $error = $this->getControlError();
      if ($error !== null) {
        $control->setError($error);
      }
    } else if ($this->getIsRequired()) {
      $control->setError(true);
    }

    return $control;
  }

  protected function renderControl() {
    $control = $this->buildControl();
    if ($control === null) {
      return null;
    }

    if ($this->getIsPreview()) {
      $disabled = true;
      $hidden = false;
    } else if ($this->getIsEditDefaults()) {
      $disabled = false;
      $hidden = false;
    } else {
      $disabled = $this->getIsLocked();
      $hidden = $this->getIsHidden();
    }

    if ($hidden) {
      return null;
    }

    $control->setDisabled($disabled);

    return $control;
  }

  public function appendToForm(AphrontFormView $form) {
    $control = $this->renderControl();
    if ($control !== null) {

      if ($this->getIsPreview()) {
        if ($this->getIsHidden()) {
          $control
            ->addClass('aphront-form-preview-hidden')
            ->setError(pht('Hidden'));
        } else if ($this->getIsLocked()) {
          $control
            ->setError(pht('Locked'));
        }
      }

      $form->appendControl($control);
    }
    return $this;
  }

  protected function getValueForControl() {
    return $this->getValue();
  }

  public function getValueForDefaults() {
    $value = $this->getValue();

    // By default, just treat the empty string like `null` since they're
    // equivalent for almost all fields and this reduces the number of
    // meaningless transactions we generate when adjusting defaults.
    if ($value === '') {
      return null;
    }

    return $value;
  }

  protected function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    $this->hasValue = true;
    $this->initialValue = $value;
    $this->value = $value;
    return $this;
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
  }

  public function getValueForTransaction() {
    return $this->getValue();
  }

  public function getTransactionType() {
    return $this->transactionType;
  }

  public function setTransactionType($type) {
    $this->transactionType = $type;
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $check = $this->getAllReadValueFromRequestKeys();
    foreach ($check as $key) {
      if (!$this->getValueExistsInRequest($request, $key)) {
        continue;
      }

      $this->value = $this->getValueFromRequest($request, $key);
      break;
    }
    return $this;
  }

  public function getAllReadValueFromRequestKeys() {
    $keys = array();

    $keys[] = $this->getKey();
    foreach ($this->getAliases() as $alias) {
      $keys[] = $alias;
    }

    return $keys;
  }

  public function readDefaultValueFromConfiguration($value) {
    $this->value = $this->getDefaultValueFromConfiguration($value);
    return $this;
  }

  protected function getDefaultValueFromConfiguration($value) {
    return $value;
  }

  protected function getValueFromObject($object) {
    if ($this->hasValue) {
      return $this->value;
    } else {
      return $this->getDefaultValue();
    }
  }

  protected function getValueExistsInRequest(AphrontRequest $request, $key) {
    return $this->getHTTPParameterValueExists($request, $key);
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getHTTPParameterValue($request, $key);
  }


  /**
   * Read and return the value the object had when the user first loaded the
   * form.
   *
   * This is the initial value from the user's point of view when they started
   * the edit process, and used primarily to prevent race conditions for fields
   * like "Projects" and "Subscribers" that use tokenizers and support edge
   * transactions.
   *
   * Most fields do not need to store these values or deal with initial value
   * handling.
   *
   * @param AphrontRequest Request to read from.
   * @param string Key to read.
   * @return wild Value read from request.
   */
  protected function getInitialValueFromSubmit(AphrontRequest $request, $key) {
    return null;
  }

  public function getInitialValue() {
    return $this->initialValue;
  }

  public function readValueFromSubmit(AphrontRequest $request) {
    $key = $this->getKey();
    if ($this->getValueExistsInSubmit($request, $key)) {
      $value = $this->getValueFromSubmit($request, $key);
    } else {
      $value = $this->getDefaultValue();
    }
    $this->value = $value;

    $initial_value = $this->getInitialValueFromSubmit($request, $key);
    $this->initialValue = $initial_value;

    return $this;
  }

  protected function getValueExistsInSubmit(AphrontRequest $request, $key) {
    return $this->getHTTPParameterValueExists($request, $key);
  }

  protected function getValueFromSubmit(AphrontRequest $request, $key) {
    return $this->getHTTPParameterValue($request, $key);
  }

  protected function getHTTPParameterValueExists(
    AphrontRequest $request,
    $key) {
    $type = $this->getHTTPParameterType();

    if ($type) {
      return $type->getExists($request, $key);
    }

    return false;
  }

  protected function getHTTPParameterValue($request, $key) {
    $type = $this->getHTTPParameterType();

    if ($type) {
      return $type->getValue($request, $key);
    }

    return null;
  }

  protected function getDefaultValue() {
    $type = $this->getHTTPParameterType();

    if ($type) {
      return $type->getDefaultValue();
    }

    return null;
  }

  final public function getHTTPParameterType() {
    $type = $this->newHTTPParameterType();

    if ($type) {
      $type->setViewer($this->getViewer());
    }

    return $type;
  }

  protected function newHTTPParameterType() {
    return new AphrontStringHTTPParameterType();
  }

  public function setEditTypeKey($edit_type_key) {
    $this->editTypeKey = $edit_type_key;
    return $this;
  }

  public function getEditTypeKey() {
    if ($this->editTypeKey === null) {
      return $this->getKey();
    }
    return $this->editTypeKey;
  }

  protected function newEditType() {
    return id(new PhabricatorSimpleEditType())
      ->setValueType($this->getHTTPParameterType()->getTypeName());
  }

  protected function getEditType() {
    $transaction_type = $this->getTransactionType();

    if ($transaction_type === null) {
      return null;
    }

    $type_key = $this->getEditTypeKey();

    return $this->newEditType()
      ->setEditType($type_key)
      ->setTransactionType($transaction_type)
      ->setDescription($this->getDescription())
      ->setMetadata($this->getMetadata());
  }

  public function getConduitEditTypes() {
    $edit_type = $this->getEditType();

    if ($edit_type === null) {
      return null;
    }

    return array($edit_type);
  }

  public function getWebEditTypes() {
    $edit_type = $this->getEditType();

    if ($edit_type === null) {
      return null;
    }

    return array($edit_type);
  }

  public function getCommentEditTypes() {
    return array();
  }

}
