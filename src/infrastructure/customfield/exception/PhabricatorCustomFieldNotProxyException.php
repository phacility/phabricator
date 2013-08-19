<?php

final class PhabricatorCustomFieldNotProxyException
  extends Exception {

  public function __construct(PhabricatorCustomField $field) {
    $key = $field->getFieldKey();
    $name = $field->getFieldName();
    $class = get_class($field);

    parent::__construct(
      "Custom field '{$name}' (with key '{$key}', of class '{$class}') can ".
      "not have a proxy set with setProxy(), because it returned false from ".
      "canSetProxy().");
  }

}
