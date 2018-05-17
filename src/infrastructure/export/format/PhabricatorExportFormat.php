<?php

abstract class PhabricatorExportFormat
  extends Phobject {

  private $viewer;
  private $title;

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

  final public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  final public function getTitle() {
    return $this->title;
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

}
