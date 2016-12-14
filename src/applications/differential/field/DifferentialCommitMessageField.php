<?php

abstract class DifferentialCommitMessageField
  extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  abstract public function getFieldName();

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

  protected function isCustomFieldEnabled($key) {
    $field_list = PhabricatorCustomField::getObjectFields(
      new DifferentialRevision(),
      PhabricatorCustomField::ROLE_VIEW);

    $fields = $field_list->getFields();
    return isset($fields[$key]);
  }

}
