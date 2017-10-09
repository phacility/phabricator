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
  private $fileData;

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

    list($format, $data) = $this->formatDataForStorage($text);

    $this->dataFormat = $format;
    $this->data = $data;

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

  private function formatDataForStorage($data) {
    $deflated = PhabricatorCaches::maybeDeflateData($data);
    if ($deflated !== null) {
      return array(self::DATAFORMAT_DEFLATED, $deflated);
    }

    return array(self::DATAFORMAT_RAW, $data);
  }

  public function saveAsText() {
    $old_type = $this->getDataType();
    $old_data = $this->getData();

    if ($old_type == self::DATATYPE_TEXT) {
      return $this;
    }

    $raw_data = $this->getRawData();

    $this->setDataType(self::DATATYPE_TEXT);

    list($format, $data) = $this->formatDataForStorage($raw_data);
    $this->setDataFormat($format);
    $this->setData($data);

    $result = $this->save();

    $this->destroyData($old_type, $old_data);

    return $result;
  }

  public function saveAsFile() {
    $old_type = $this->getDataType();
    $old_data = $this->getData();

    if ($old_type == self::DATATYPE_FILE) {
      return $this;
    }

    $raw_data = $this->getRawData();

    list($format, $data) = $this->formatDataForStorage($raw_data);
    $this->setDataFormat($format);

    $file = PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => 'differential-hunk',
        'mime-type' => 'application/octet-stream',
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));

    $this->setDataType(self::DATATYPE_FILE);
    $this->setData($file->getPHID());

    // NOTE: Because hunks don't have a PHID and we just load hunk data with
    // the omnipotent viewer, we do not need to attach the file to anything.

    $result = $this->save();

    $this->destroyData($old_type, $old_data);

    return $result;
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
          $data = $this->loadFileData();
          break;
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

  private function loadFileData() {
    if ($this->fileData === null) {
      $type = $this->getDataType();
      if ($type !== self::DATATYPE_FILE) {
        throw new Exception(
          pht(
            'Unable to load file data for hunk with wrong data type ("%s").',
            $type));
      }

      $file_phid = $this->getData();

      $file = $this->loadRawFile($file_phid);
      $data = $file->loadFileData();

      $this->fileData = $data;
    }

    return $this->fileData;
  }

  private function loadRawFile($file_phid) {
    $viewer = PhabricatorUser::getOmnipotentUser();


    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->execute();
    if (!$files) {
      throw new Exception(
        pht(
          'Failed to load file ("%s") with hunk data.',
          $file_phid));
    }

    $file = head($files);

    return $file;
  }


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $type = $this->getDataType();
    $data = $this->getData();

    $this->destroyData($type, $data, $engine);

    return parent::destroyObjectPermanently($engine);
  }


  private function destroyData(
    $type,
    $data,
    PhabricatorDestructionEngine $engine = null) {

    if (!$engine) {
      $engine = new PhabricatorDestructionEngine();
    }

    switch ($type) {
      case self::DATATYPE_FILE:
        $file = $this->loadRawFile($data);
        $engine->destroyObject($file);
        break;
    }
  }

}
