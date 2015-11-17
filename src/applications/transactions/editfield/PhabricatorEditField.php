<?php

abstract class PhabricatorEditField extends Phobject {

  private $key;
  private $viewer;
  private $label;
  private $aliases = array();
  private $value;
  private $hasValue = false;
  private $object;
  private $transactionType;
  private $metadata = array();
  private $description;
  private $editTypeKey;
  private $isLocked;

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

  protected function newControl() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function renderControl() {
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

    if ($this->getIsLocked()) {
      $control->setDisabled(true);
    }

    return $control;
  }

  public function appendToForm(AphrontFormView $form) {
    $control = $this->renderControl();
    if ($control !== null) {
      $form->appendControl($control);
    }
    return $this;
  }

  protected function getValueForControl() {
    return $this->getValue();
  }

  protected function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    $this->hasValue = true;
    $this->value = $value;
    return $this;
  }

  public function generateTransaction(
    PhabricatorApplicationTransaction $xaction) {

    $xaction
      ->setTransactionType($this->getTransactionType())
      ->setNewValue($this->getValueForTransaction());

    foreach ($this->metadata as $key => $value) {
      $xaction->setMetadataValue($key, $value);
    }

    return $xaction;
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  protected function getValueForTransaction() {
    return $this->getValue();
  }

  public function getTransactionType() {
    if (!$this->transactionType) {
      throw new PhutilInvalidStateException('setTransactionType');
    }
    return $this->transactionType;
  }

  public function setTransactionType($type) {
    $this->transactionType = $type;
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $check = array_merge(array($this->getKey()), $this->getAliases());
    foreach ($check as $key) {
      if (!$this->getValueExistsInRequest($request, $key)) {
        continue;
      }

      $this->value = $this->getValueFromRequest($request, $key);
      return;
    }

    $this->readValueFromObject($this->getObject());

    return $this;
  }

  public function readValueFromObject($object) {
    $this->value = $this->getValueFromObject($object);
    return $this;
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
    return $this->getValueExistsInSubmit($request, $key);
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getValueFromSubmit($request, $key);
  }

  public function readValueFromSubmit(AphrontRequest $request) {
    $key = $this->getKey();
    if ($this->getValueExistsInSubmit($request, $key)) {
      $value = $this->getValueFromSubmit($request, $key);
    } else {
      $value = $this->getDefaultValue();
    }
    $this->value = $value;
    return $this;
  }

  protected function getValueExistsInSubmit(AphrontRequest $request, $key) {
    return $this->getHTTPParameterType()->getExists($request, $key);
  }

  protected function getValueFromSubmit(AphrontRequest $request, $key) {
    return $this->getHTTPParameterType()->getValue($request, $key);
  }

  protected function getDefaultValue() {
    return $this->getHTTPParameterType()->getDefaultValue();
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

  public function getEditTransactionTypes() {
    $transaction_type = $this->getTransactionType();
    $type_key = $this->getEditTypeKey();

    // TODO: This is a pretty big pile of hard-coded hacks for now.

    $edge_types = array(
      PhabricatorTransactions::TYPE_EDGE => array(
        '+' => pht('Add projects.'),
        '-' => pht('Remove projects.'),
        '=' => pht('Set associated projects, overwriting current value.'),
      ),
      PhabricatorTransactions::TYPE_SUBSCRIBERS => array(
        '+' => pht('Add subscribers.'),
        '-' => pht('Remove subscribers.'),
        '=' => pht('Set subscribers, overwriting current value.'),
      ),
    );

    if (isset($edge_types[$transaction_type])) {
      $base = id(new PhabricatorEdgeEditType())
        ->setTransactionType($transaction_type)
        ->setMetadata($this->metadata);

      $strings = $edge_types[$transaction_type];

      $add = id(clone $base)
        ->setEditType($type_key.'.add')
        ->setEdgeOperation('+')
        ->setDescription($strings['+'])
        ->setValueDescription(pht('List of PHIDs to add.'));
      $rem = id(clone $base)
        ->setEditType($type_key.'.remove')
        ->setEdgeOperation('-')
        ->setDescription($strings['-'])
        ->setValueDescription(pht('List of PHIDs to remove.'));
      $set = id(clone $base)
        ->setEditType($type_key.'.set')
        ->setEdgeOperation('=')
        ->setDescription($strings['='])
        ->setValueDescription(pht('List of PHIDs to set.'));

      return array(
        $add,
        $rem,
        $set,
      );
    }

    return array(
      id(new PhabricatorSimpleEditType())
        ->setEditType($type_key)
        ->setTransactionType($transaction_type)
        ->setValueType($this->getHTTPParameterType()->getTypeName())
        ->setDescription($this->getDescription())
        ->setMetadata($this->metadata),
    );
  }

}
