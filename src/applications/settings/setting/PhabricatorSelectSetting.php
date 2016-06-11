<?php

abstract class PhabricatorSelectSetting
  extends PhabricatorSetting {

  abstract protected function getSelectOptions();

  final protected function newCustomEditField($object) {
    $setting_key = $this->getSettingKey();
    $default_value = $object->getDefaultValue($setting_key);

    $options = $this->getSelectOptions();

    if (isset($options[$default_value])) {
      $default_label = pht('Default (%s)', $options[$default_value]);
    } else {
      $default_label = pht('Default (Unknown, "%s")', $default_value);
    }

    if (empty($options[''])) {
      $options = array(
        '' => $default_label,
      ) + $options;
    }

    return $this->newEditField($object, new PhabricatorSelectEditField())
      ->setOptions($options);
  }

  public function assertValidValue($value) {
    // This is a slightly stricter check than the transaction check. It's
    // OK for empty string to go through transactions because it gets converted
    // to null later, but we shouldn't be reading the empty string from
    // storage.
    if ($value === null) {
      return;
    }

    if (!strlen($value)) {
      throw new Exception(
        pht(
          'Empty string is not a valid setting for "%s".',
          $this->getSettingName()));
    }

    $this->validateTransactionValue($value);
  }

  final public function validateTransactionValue($value) {
    if (!strlen($value)) {
      return;
    }

    $options = $this->getSelectOptions();

    if (!isset($options[$value])) {
      throw new Exception(
        pht(
          'Value "%s" is not valid for setting "%s": valid values are %s.',
          $value,
          $this->getSettingName(),
          implode(', ', array_keys($options))));
    }

    return;
  }

  public function getTransactionNewValue($value) {
    if (!strlen($value)) {
      return null;
    }

    return (string)$value;
  }

}
