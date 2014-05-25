<?php

final class DifferentialHunkModern extends DifferentialHunk {

  const DATATYPE_TEXT       = 'text';
  const DATATYPE_FILE       = 'file';

  const DATAFORMAT_RAW      = 'byte';
  const DATAFORMAT_DEFLATE  = 'gzde';

  protected $dataType;
  protected $dataEncoding;
  protected $dataFormat;
  protected $data;

  public function getTableName() {
    return 'differential_hunk_modern';
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_BINARY => array(
        'data' => true,
      ),
    ) + parent::getConfiguration();
  }

  public function setChanges($text) {
    $this->dataEncoding = $this->detectEncodingForStorage($text);
    $this->dataType = self::DATATYPE_TEXT;
    $this->dataFormat = self::DATAFORMAT_RAW;
    $this->data = $text;

    return $this;
  }

  public function getChanges() {
    return $this->getUTF8StringFromStorage(
      $this->getRawData(),
      $this->getDataEncoding());
  }

  private function getRawData() {
    $type = $this->getDataType();
    $data = $this->getData();

    switch ($type) {
      case self::DATATYPE_TEXT:
        // In this storage type, the changes are stored on the object.
        $data = $data;
        break;
      case self::DATATYPE_FILE:
      default:
        throw new Exception(
          pht('Hunk has unsupported data type "%s"!', $type));
    }

    $format = $this->getDataFormat();
    switch ($format) {
      case self::DATAFORMAT_RAW:
        // In this format, the changes are stored as-is.
        $data = $data;
        break;
      case self::DATAFORMAT_DEFLATE:
      default:
        throw new Exception(
          pht('Hunk has unsupported data encoding "%s"!', $type));
    }

    return $data;
  }

}
