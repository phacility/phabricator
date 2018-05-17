<?php

abstract class PhabricatorMailStamp
  extends Phobject {

  private $key;
  private $value;
  private $label;
  private $viewer;

  final public function getStampType() {
    return $this->getPhobjectClassConstant('STAMPTYPE');
  }

  final public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  final public function getKey() {
    return $this->key;
  }

  final protected function setRawValue($value) {
    $this->value = $value;
    return $this;
  }

  final protected function getRawValue() {
    return $this->value;
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  final public function getLabel() {
    return $this->label;
  }

  public function setValue($value) {
    return $this->setRawValue($value);
  }

  final public function toDictionary() {
    return array(
      'type' => $this->getStampType(),
      'key' => $this->getKey(),
      'value' => $this->getValueForDictionary(),
    );
  }

  final public static function getAllStamps() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getStampType')
      ->execute();
  }

  protected function getValueForDictionary() {
    return $this->getRawValue();
  }

  public function setValueFromDictionary($value) {
    return $this->setRawValue($value);
  }

  public function getValueForRendering() {
    return $this->getRawValue();
  }

  abstract public function renderStamps($value);

  final protected function renderStamp($key, $value = null) {
    return $key.'('.$value.')';
  }

}
