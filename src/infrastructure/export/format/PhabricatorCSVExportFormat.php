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

  public function addHeaders(array $fields) {
    $headers = mpull($fields, 'getLabel');
    $this->addRow($headers);
  }

  public function addObject($object, array $fields, array $map) {
    $values = array();
    foreach ($fields as $key => $field) {
      $value = $map[$key];
      $value = $field->getTextValue($value);
      $values[] = $value;
    }

    $this->addRow($values);
  }

  private function addRow(array $values) {
    $row = array();
    foreach ($values as $value) {

      // Excel is extremely interested in executing arbitrary code it finds in
      // untrusted CSV files downloaded from the internet. When a cell looks
      // like it might be too tempting for Excel to ignore, mangle the value
      // to dissuade remote code execution. See T12800.

      if (preg_match('/^\s*[+=@-]/', $value)) {
        $value = '(!) '.$value;
      }

      if (preg_match('/\s|,|\"/', $value)) {
        $value = str_replace('"', '""', $value);
        $value = '"'.$value.'"';
      }

      $row[] = $value;
    }

    $this->rows[] = implode(',', $row);
  }

  public function newFileData() {
    return implode("\n", $this->rows);
  }

}
