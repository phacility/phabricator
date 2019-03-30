<?php

final class PhabricatorOptionExportField
  extends PhabricatorExportField {

  private $options;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function getNaturalValue($value) {
    if ($value === null) {
      return $value;
    }

    if (!strlen($value)) {
      return null;
    }

    $options = $this->getOptions();

    return array(
      'value' => (string)$value,
      'name' => (string)idx($options, $value, $value),
    );
  }

  public function getTextValue($value) {
    $natural_value = $this->getNaturalValue($value);
    if ($natural_value === null) {
      return null;
    }

    return $natural_value['name'];
  }

  public function getPHPExcelValue($value) {
    return $this->getTextValue($value);
  }

}
