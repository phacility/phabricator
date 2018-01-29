<?php

abstract class PhabricatorExportFormat
  extends Phobject {

  private $viewer;

  final public function getExportFormatKey() {
    return $this->getPhobjectClassConstant('EXPORTKEY');
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  abstract public function getExportFormatName();
  abstract public function getMIMEContentType();
  abstract public function getFileExtension();

  public function addHeaders(array $fields) {
    return;
  }

  abstract public function addObject($object, array $fields, array $map);
  abstract public function newFileData();

  public function isExportFormatEnabled() {
    return true;
  }

  final public static function getAllExportFormats() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExportFormatKey')
      ->execute();
  }

  final public static function getAllEnabledExportFormats() {
    $formats = self::getAllExportFormats();

    foreach ($formats as $key => $format) {
      if (!$format->isExportFormatEnabled()) {
        unset($formats[$key]);
      }
    }

    return $formats;
  }

}
