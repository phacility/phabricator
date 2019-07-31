<?php

final class PhabricatorEpochExportField
  extends PhabricatorExportField {

  private $zone;

  public function getTextValue($value) {
    if ($value === null) {
      return '';
    }

    if (!isset($this->zone)) {
      $this->zone = new DateTimeZone('UTC');
    }

    try {
      $date = new DateTime('@'.$value);
    } catch (Exception $ex) {
      return null;
    }

    $date->setTimezone($this->zone);
    return $date->format('c');
  }

  public function getNaturalValue($value) {
    if ($value === null) {
      return $value;
    }

    return (int)$value;
  }

  public function getPHPExcelValue($value) {
    $epoch = $this->getNaturalValue($value);

    if ($epoch === null) {
      return null;
    }

    $seconds_per_day = phutil_units('1 day in seconds');
    $offset = ($seconds_per_day * 25569);

    return ($epoch + $offset) / $seconds_per_day;
  }

  /**
   * @phutil-external-symbol class PHPExcel_Style_NumberFormat
   */
  public function formatPHPExcelCell($cell, $style) {
    $code = PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2;

    $style
      ->getNumberFormat()
      ->setFormatCode($code);
  }

}
