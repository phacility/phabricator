<?php

abstract class PhabricatorExportField
  extends Phobject {

  private $key;
  private $label;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function getTextValue($value) {
    $natural_value = $this->getNaturalValue($value);

    if ($natural_value === null) {
      return null;
    }

    return (string)$natural_value;
  }

  public function getNaturalValue($value) {
    return $value;
  }

  public function getPHPExcelValue($value) {
    return $this->getTextValue($value);
  }

  /**
   * @phutil-external-symbol class PHPExcel_Cell_DataType
   */
  public function formatPHPExcelCell($cell, $style) {
    $cell->setDataType(PHPExcel_Cell_DataType::TYPE_STRING);
  }

  public function getCharacterWidth() {
    return 24;
  }

}
