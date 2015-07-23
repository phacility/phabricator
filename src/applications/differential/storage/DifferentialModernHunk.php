<?php

final class DifferentialModernHunk extends DifferentialHunk {

  const DATATYPE_TEXT       = 'text';
  const DATATYPE_FILE       = 'file';

  const DATAFORMAT_RAW      = 'byte';
  const DATAFORMAT_DEFLATED = 'gzde';

  protected $dataType;
  protected $dataEncoding;
  protected $dataFormat;
  protected $data;

  private $rawData;
  private $forcedEncoding;

  public function getTableName() {
    return 'differential_hunk_modern';
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_BINARY => array(
        'data' => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'dataType' => 'bytes4',
        'dataEncoding' => 'text16?',
        'dataFormat' => 'bytes4',
        'oldOffset' => 'uint32',
        'oldLen' => 'uint32',
        'newOffset' => 'uint32',
        'newLen' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_changeset' => array(
          'columns' => array('changesetID'),
        ),
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setChanges($text) {
    $this->rawData = $text;

    $this->dataEncoding = $this->detectEncodingForStorage($text);
    $this->dataType = self::DATATYPE_TEXT;
    $this->dataFormat = self::DATAFORMAT_RAW;
    $this->data = $text;

    return $this;
  }

  public function getChanges() {
    return $this->getUTF8StringFromStorage(
      $this->getRawData(),
      nonempty($this->forcedEncoding, $this->getDataEncoding()));
  }

  public function forceEncoding($encoding) {
    $this->forcedEncoding = $encoding;
    return $this;
  }

  public function save() {

    $type = $this->getDataType();
    $format = $this->getDataFormat();

    // Before saving the data, attempt to compress it.
    if ($type == self::DATATYPE_TEXT) {
      if ($format == self::DATAFORMAT_RAW) {
        $data = $this->getData();
        $deflated = PhabricatorCaches::maybeDeflateData($data);
        if ($deflated !== null) {
          $this->data = $deflated;
          $this->dataFormat = self::DATAFORMAT_DEFLATED;
        }
      }
    }

    return parent::save();
  }

  private function getRawData() {
    if ($this->rawData === null) {
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
        case self::DATAFORMAT_DEFLATED:
          $data = PhabricatorCaches::inflateData($data);
          break;
        default:
          throw new Exception(
            pht('Hunk has unsupported data encoding "%s"!', $type));
      }

      $this->rawData = $data;
    }

    return $this->rawData;
  }

}
