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
  private $editTypeKey;
  private $isRequired;
  private $previewPanel;
  private $controlID;
  private $controlInstructions;

  private $description;
  private $conduitDescription;
  private $conduitDocumentation;
  private $conduitTypeDescription;

  private $commentActionLabel;
  private $commentActionValue;
  private $commentActionOrder = 1000;
  private $hasCommentActionValue;

  private $isLocked;
  private $isHidden;

  private $isPreview;
  private $isEditDefaults;
  private $isSubmittedForm;
  private $controlError;

  private $isReorderable = true;
  private $isDefaultable = true;
  private $isLockable = true;
  private $isCopyable = false;
  private $isConduitOnly = false;

  private $conduitEditTypes;

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

  public function setIsConduitOnly($is_conduit_only) {
    $this->isConduitOnly = $is_conduit_only;
    return $this;
  }

  public function getIsConduitOnly() {
    return $this->isConduitOnly;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setConduitDescription($conduit_description) {
    $this->conduitDescription = $conduit_description;
    return $this;
  }

  public function getConduitDescription() {
    if ($this->conduitDescription === null) {
      return $this->getDescription();
    }
    return $this->conduitDescription;
  }

  public function setConduitDocumentation($conduit_documentation) {
    $this->conduitDocumentation = $conduit_documentation;
    return $this;
  }

  public function getConduitDocumentation() {
    return $this->conduitDocumentation;
  }

  public function setConduitTypeDescription($conduit_type_description) {
    $this->conduitTypeDescription = $conduit_type_description;
    return $this;
  }

  public function getConduitTypeDescription() {
    return $this->conduitTypeDescription;
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

  public function setIsCopyable($is_copyable) {
    $this->isCopyable = $is_copyable;
    return $this;
  }

  public function getIsCopyable() {
    return $this->isCopyable;
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

  public function setCommentActionLabel($label) {
    $this->commentActionLabel = $label;
    return $this;
  }

  public function getCommentActionLabel() {
    return $this->commentActionLabel;
  }

  public function setCommentActionOrder($order) {
    $this->commentActionOrder = $order;
    return $this;
  }

  public function getCommentActionOrder() {
    return $this->commentActionOrder;
  }

  public function setCommentActionValue($comment_action_value) {
    $this->hasCommentActionValue = true;
    $this->commentActionValue = $comment_action_value;
    return $this;
  }

  public function getCommentActionValue() {
    return $this->commentActionValue;
  }

  public function setPreviewPanel(PHUIRemarkupPreviewPanel $preview_panel) {
    $this->previewPanel = $preview_panel;
    return $this;
  }

  public function getPreviewPanel() {
    return $this->previewPanel;
  }

  public function setControlInstructions($control_instructions) {
    $this->controlInstructions = $control_instructions;
    return $this;
  }

  public function getControlInstructions() {
    return $this->controlInstructions;
  }

  protected function newControl() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function buildControl() {
    if ($this->getIsConduitOnly()) {
      return null;
    }

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

  public function getControlID() {
    if (!$this->controlID) {
      $this->controlID = celerity_generate_unique_node_id();
    }
    return $this->controlID;
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

    if ($this->controlID) {
      $control->setID($this->controlID);
    }

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

      $instructions = $this->getControlInstructions();
      if (strlen($instructions)) {
        $form->appendRemarkupInstructions($instructions);
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

  public function readValueFromComment($value) {
    $this->value = $this->getValueFromComment($value);
    return $this;
  }

  protected function getValueFromComment($value) {
    return $value;
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

  public function readValueFromField(PhabricatorEditField $other) {
    $this->value = $this->getValueFromField($other);
    return $this;
  }

  protected function getValueFromField(PhabricatorEditField $other) {
    return $other->getValue();
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

  public function setInitialValue($initial_value) {
    $this->initialValue = $initial_value;
    return $this;
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
    if ($this->getIsConduitOnly()) {
      return null;
    }

    $type = $this->newHTTPParameterType();

    if ($type) {
      $type->setViewer($this->getViewer());
    }

    return $type;
  }

  protected function newHTTPParameterType() {
    return new AphrontStringHTTPParameterType();
  }

  public function getConduitParameterType() {
    $type = $this->newConduitParameterType();

    if (!$type) {
      return null;
    }

    $type->setViewer($this->getViewer());

    return $type;
  }

  abstract protected function newConduitParameterType();

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
    $parameter_type = $this->getConduitParameterType();
    if (!$parameter_type) {
      return null;
    }

    return id(new PhabricatorSimpleEditType())
      ->setConduitParameterType($parameter_type);
  }

  protected function getEditType() {
    $transaction_type = $this->getTransactionType();

    if ($transaction_type === null) {
      return null;
    }

    $type_key = $this->getEditTypeKey();
    $edit_type = $this->newEditType();
    if (!$edit_type) {
      return null;
    }

    return $edit_type
      ->setEditType($type_key)
      ->setTransactionType($transaction_type)
      ->setMetadata($this->getMetadata());
  }

  final public function getConduitEditTypes() {
    if ($this->conduitEditTypes === null) {
      $edit_types = $this->newConduitEditTypes();
      $edit_types = mpull($edit_types, null, 'getEditType');

      foreach ($edit_types as $edit_type) {
        $edit_type->setEditField($this);
      }

      $this->conduitEditTypes = $edit_types;
    }

    return $this->conduitEditTypes;
  }

  final public function getConduitEditType($key) {
    $edit_types = $this->getConduitEditTypes();

    if (empty($edit_types[$key])) {
      throw new Exception(
        pht(
          'This EditField does not provide a Conduit EditType with key "%s".',
          $key));
    }

    return $edit_types[$key];
  }

  protected function newConduitEditTypes() {
    $edit_type = $this->getEditType();

    if (!$edit_type) {
      return array();
    }

    return array($edit_type);
  }

  public function getCommentAction() {
    $label = $this->getCommentActionLabel();
    if ($label === null) {
      return null;
    }

    $action = $this->newCommentAction();
    if ($action === null) {
      return null;
    }

    if ($this->hasCommentActionValue) {
      $value = $this->getCommentActionValue();
    } else {
      $value = $this->getValue();
    }

    $action
      ->setKey($this->getKey())
      ->setLabel($label)
      ->setValue($this->getValueForCommentAction($value))
      ->setOrder($this->getCommentActionOrder());

    return $action;
  }

  protected function newCommentAction() {
    return null;
  }

  protected function getValueForCommentAction($value) {
    return $value;
  }

  public function shouldGenerateTransactionsFromSubmit() {
    if ($this->getIsConduitOnly()) {
      return false;
    }

    $edit_type = $this->getEditType();
    if (!$edit_type) {
      return false;
    }

    return true;
  }

  public function shouldReadValueFromRequest() {
    if ($this->getIsConduitOnly()) {
      return false;
    }

    if ($this->getIsLocked()) {
      return false;
    }

    if ($this->getIsHidden()) {
      return false;
    }

    return true;
  }

  public function shouldReadValueFromSubmit() {
    if ($this->getIsConduitOnly()) {
      return false;
    }

    if ($this->getIsLocked()) {
      return false;
    }

    if ($this->getIsHidden()) {
      return false;
    }

    return true;
  }

  public function shouldGenerateTransactionsFromComment() {
    if ($this->getIsConduitOnly()) {
      return false;
    }

    if ($this->getIsLocked()) {
      return false;
    }

    if ($this->getIsHidden()) {
      return false;
    }

    return true;
  }

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $edit_type = $this->getEditType();
    if (!$edit_type) {
      throw new Exception(
        pht(
          'EditField (with key "%s", of class "%s") is generating '.
          'transactions, but has no EditType.',
          $this->getKey(),
          get_class($this)));
    }

    return $edit_type->generateTransactions($template, $spec);
  }

}
