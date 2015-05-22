<?php

final class PhabricatorCustomFieldNotProxyException extends Exception {

  public function __construct(PhabricatorCustomField $field) {
    parent::__construct(
      pht(
        "Custom field '%s' (with key '%s', of class '%s') can not have a ".
        "proxy set with %s, because it returned %s from %s.",
        $field->getFieldName(),
        $field->getFieldKey(),
        get_class($field),
        'setProxy()',
        'false',
        'canSetProxy()'));
  }

}
