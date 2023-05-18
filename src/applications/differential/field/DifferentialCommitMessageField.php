<?php

abstract class DifferentialCommitMessageField
  extends Phobject {

  private $viewer;
  private $customFieldStorage;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setCustomFieldStorage(array $custom_field_storage) {
    $this->customFieldStorage = $custom_field_storage;
    return $this;
  }

  final public function getCustomFieldStorage() {
    return $this->customFieldStorage;
  }

  abstract public function getFieldName();
  abstract public function getFieldOrder();

  public function isFieldEnabled() {
    return true;
  }

  public function getFieldAliases() {
    return array();
  }

  public function validateFieldValue($value) {
    return;
  }

  public function parseFieldValue($value) {
    return $value;
  }

  public function isFieldEditable() {
    return true;
  }

  public function isTemplateField() {
    return true;
  }

  public function readFieldValueFromConduit($value) {
    return $this->readStringFieldValueFromConduit($value);
  }

  public function readFieldValueFromObject(DifferentialRevision $revision) {
    return null;
  }

  public function renderFieldValue($value) {
    if ($value === null || !strlen($value)) {
      return null;
    }

    return $value;
  }

  public function getFieldTransactions($value) {
    if (!$this->isFieldEditable()) {
      return array();
    }
    throw new PhutilMethodNotImplementedException();
  }

  final public function getCommitMessageFieldKey() {
    return $this->getPhobjectClassConstant('FIELDKEY', 64);
  }

  final public static function newEnabledFields(PhabricatorUser $viewer) {
    $fields = self::getAllFields();

    $results = array();
    foreach ($fields as $key => $field) {
      $field = clone $field;
      $field->setViewer($viewer);
      if ($field->isFieldEnabled()) {
        $results[$key] = $field;
      }
    }

    return $results;
  }

  final public static function getAllFields() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getCommitMessageFieldKey')
      ->setSortMethod('getFieldOrder')
      ->execute();
  }

  protected function raiseParseException($message) {
    throw new DifferentialFieldParseException($message);
  }

  protected function raiseValidationException($message) {
    throw new DifferentialFieldValidationException($message);
  }

  protected function parseObjectList(
    $value,
    array $types,
    $allow_partial = false,
    array $suffixes = array()) {
    return id(new PhabricatorObjectListQuery())
      ->setViewer($this->getViewer())
      ->setAllowedTypes($types)
      ->setObjectList($value)
      ->setAllowPartialResults($allow_partial)
      ->setSuffixes($suffixes)
      ->execute();
  }

  protected function renderHandleList(array $phids, array $suffixes = array()) {
    if (!$phids) {
      return null;
    }

    $handles = $this->getViewer()->loadHandles($phids);

    $out = array();
    foreach ($handles as $handle) {
      $phid = $handle->getPHID();

      if ($handle->getPolicyFiltered()) {
        $token = $phid;
      } else if ($handle->isComplete()) {
        $token = $handle->getCommandLineObjectName();
      }

      $suffix = idx($suffixes, $phid);
      $token = $token.$suffix;

      $out[] = $token;
    }

    return implode(', ', $out);
  }

  protected function readStringFieldValueFromConduit($value) {
    if ($value === null) {
      return $value;
    }

    if (!is_string($value)) {
      throw new Exception(
        pht(
          'Field "%s" expects a string value, but received a value of type '.
          '"%s".',
          $this->getCommitMessageFieldKey(),
          gettype($value)));
    }

    return $value;
  }

  protected function readStringListFieldValueFromConduit($value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Field "%s" expects a list of strings, but received a value of type '.
          '"%s".',
          $this->getCommitMessageFieldKey(),
          gettype($value)));
    }

    return $value;
  }

  protected function isCustomFieldEnabled($key) {
    $field_list = PhabricatorCustomField::getObjectFields(
      new DifferentialRevision(),
      DifferentialCustomField::ROLE_DEFAULT);

    $fields = $field_list->getFields();
    return isset($fields[$key]);
  }

}
