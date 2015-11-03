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

  abstract protected function newControl();

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
    return $request->getExists($key);
  }

  protected function getValueFromSubmit(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function getDefaultValue() {
    return null;
  }

  protected function getListFromRequest(
    AphrontRequest $request,
    $key) {

    $list = $request->getArr($key, null);
    if ($list === null) {
      $list = $request->getStrList($key);
    }

    if (!$list) {
      return array();
    }

    return $list;
  }

}
