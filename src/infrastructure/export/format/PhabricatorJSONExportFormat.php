<?php

final class PhabricatorJSONExportFormat
  extends PhabricatorExportFormat {

  const EXPORTKEY = 'json';

  private $objects = array();

  public function getExportFormatName() {
    return 'JSON (.json)';
  }

  public function isExportFormatEnabled() {
    return true;
  }

  public function getFileExtension() {
    return 'json';
  }

  public function getMIMEContentType() {
    return 'application/json';
  }

  public function addObject($object, array $fields, array $map) {
    $values = array();
    foreach ($fields as $key => $field) {
      $value = $map[$key];
      $value = $field->getNaturalValue($value);

      $values[$key] = $value;
    }

    $this->objects[] = $values;
  }

  public function newFileData() {
    return id(new PhutilJSON())
      ->encodeAsList($this->objects);
  }

}
