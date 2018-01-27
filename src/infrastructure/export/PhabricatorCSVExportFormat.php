<?php

final class PhabricatorCSVExportFormat
  extends PhabricatorExportFormat {

  const EXPORTKEY = 'csv';

  private $rows = array();

  public function getExportFormatName() {
    return pht('Comma-Separated Values (.csv)');
  }

  public function isExportFormatEnabled() {
    return true;
  }

  public function getFileExtension() {
    return 'csv';
  }

  public function getMIMEContentType() {
    return 'text/csv';
  }

  public function addObject($object, array $fields, array $map) {
    $values = array();
    foreach ($fields as $key => $field) {
      $value = $map[$key];
      $value = $field->getTextValue($value);

      if (preg_match('/\s|,|\"/', $value)) {
        $value = str_replace('"', '""', $value);
        $value = '"'.$value.'"';
      }

      $values[] = $value;
    }

    $this->rows[] = implode(',', $values);
  }

  public function newFileData() {
    return implode("\n", $this->rows);
  }

}
