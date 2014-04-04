<?php

final class PhabricatorCustomFieldDataNotAvailableException
  extends Exception {

  public function __construct(PhabricatorCustomField $field) {
    $key = $field->getFieldKey();
    $name = $field->getFieldName();
    $class = get_class($field);

    parent::__construct(
      "Custom field '{$name}' (with key '{$key}', of class '{$class}') is ".
      "attempting to access data which is not available in this context.");
  }

}
