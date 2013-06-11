<?php

final class PhabricatorCustomFieldImplementationIncompleteException
  extends Exception {

  public function __construct(PhabricatorCustomField $field) {
    $key = $field->getFieldKey();
    $name = $field->getFieldName();
    $class = get_class($field);

    parent::__construct(
      "Custom field '{$name}' (with key '{$key}', of class '{$class}') is ".
      "incompletely implemented: it claims to support a feature, but does not ".
      "implement all of the required methods for that feature.");
  }

}
