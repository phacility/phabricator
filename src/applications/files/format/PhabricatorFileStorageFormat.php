<?php

abstract class PhabricatorFileStorageFormat
  extends Phobject {

  private $file;

  final public function setFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  final public function getFile() {
    if (!$this->file) {
      throw new PhutilInvalidStateException('setFile');
    }
    return $this->file;
  }

  abstract public function getStorageFormatName();

  abstract public function newReadIterator($raw_iterator);
  abstract public function newWriteIterator($raw_iterator);

  public function newStorageProperties() {
    return array();
  }

  public function canGenerateNewKeyMaterial() {
    return false;
  }

  public function generateNewKeyMaterial() {
    throw new PhutilMethodNotImplementedException();
  }

  public function canCycleMasterKey() {
    return false;
  }

  public function cycleStorageProperties() {
    throw new PhutilMethodNotImplementedException();
  }

  public function selectMasterKey($key_name) {
    throw new Exception(
      pht(
        'This storage format ("%s") does not support key selection.',
        $this->getStorageFormatName()));
  }

  final public function getStorageFormatKey() {
    return $this->getPhobjectClassConstant('FORMATKEY');
  }

  final public static function getAllFormats() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getStorageFormatKey')
      ->execute();
  }

  final public static function getFormat($key) {
    $formats = self::getAllFormats();
    return idx($formats, $key);
  }

  final public static function requireFormat($key) {
    $format = self::getFormat($key);

    if (!$format) {
      throw new Exception(
        pht(
          'No file storage format with key "%s" exists.',
          $key));
    }

    return $format;
  }

}
