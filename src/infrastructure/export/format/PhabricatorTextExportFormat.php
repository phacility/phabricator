<?php

final class PhabricatorTextExportFormat
  extends PhabricatorExportFormat {

  const EXPORTKEY = 'text';

  private $rows = array();

  public function getExportFormatName() {
    return 'Tab-Separated Text (.txt)';
  }

  public function isExportFormatEnabled() {
    return true;
  }

  public function getFileExtension() {
    return 'txt';
  }

  public function getMIMEContentType() {
    return 'text/plain';
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
      $row[] = addcslashes($value, "\0..\37\\\177..\377");
    }

    $this->rows[] = implode("\t", $row);
  }

  public function newFileData() {
    return implode("\n", $this->rows)."\n";
  }

}
