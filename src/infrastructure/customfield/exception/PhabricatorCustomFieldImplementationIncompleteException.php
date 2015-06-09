<?php

final class PhabricatorCustomFieldImplementationIncompleteException
  extends Exception {

  public function __construct(
    PhabricatorCustomField $field,
    $field_key_is_incomplete = false) {

    if ($field_key_is_incomplete) {
      $key = pht('<incomplete key>');
      $name = pht('<incomplete name>');
    } else {
      $key = $field->getFieldKey();
      $name = $field->getFieldName();
    }

    parent::__construct(
      pht(
        "Custom field '%s' (with key '%s', of class '%s') is incompletely ".
        "implemented: it claims to support a feature, but does not ".
        "implement all of the required methods for that feature.",
        $name,
        $key,
        get_class($field)));
  }

}
