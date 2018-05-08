<?php

final class PhabricatorDoubleExportField
  extends PhabricatorExportField {

  public function getNaturalValue($value) {
    if ($value === null) {
      return $value;
    }

    return (double)$value;
  }

  /**
   * @phutil-external-symbol class PHPExcel_Cell_DataType
   */
  public function formatPHPExcelCell($cell, $style) {
    $cell->setDataType(PHPExcel_Cell_DataType::TYPE_NUMERIC);
  }

  public function getCharacterWidth() {
    return 8;
  }

}
