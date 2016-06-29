<?php

abstract class PhabricatorOptionGroupSetting
  extends PhabricatorSetting {

  abstract protected function getSelectOptionGroups();

  final protected function getSelectOptionMap() {
    $groups = $this->getSelectOptionGroups();

    $map = array();
    foreach ($groups as $group) {
      $map += $group['options'];
    }

    return $map;
  }

  final protected function newCustomEditField($object) {
    $setting_key = $this->getSettingKey();
    $default_value = $object->getDefaultValue($setting_key);

    $options = $this->getSelectOptionGroups();

    $map = $this->getSelectOptionMap();
    if (isset($map[$default_value])) {
      $default_label = pht('Default (%s)', $map[$default_value]);
    } else {
      $default_label = pht('Default (Unknown, "%s")', $default_value);
    }

    $head_key = head_key($options);
    $options[$head_key]['options'] = array(
      '' => $default_label,
    ) + $options[$head_key]['options'];

    $flat_options = array();
    foreach ($options as $group) {
      $flat_options[$group['label']] = $group['options'];
    }

    return $this->newEditField($object, new PhabricatorSelectEditField())
      ->setOptions($flat_options);
  }

  final public function validateTransactionValue($value) {
    if (!strlen($value)) {
      return;
    }

    $map = $this->getSelectOptionMap();

    if (!isset($map[$value])) {
      throw new Exception(
        pht(
          'Value "%s" is not valid for setting "%s": valid values are %s.',
          $value,
          $this->getSettingName(),
          implode(', ', array_keys($map))));
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
