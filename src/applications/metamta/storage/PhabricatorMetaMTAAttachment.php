<?php

final class PhabricatorMetaMTAAttachment extends Phobject {
  protected $data;
  protected $filename;
  protected $mimetype;

  public function __construct($data, $filename, $mimetype) {
    $this->setData($data);
    $this->setFilename($filename);
    $this->setMimeType($mimetype);
  }

  public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getFilename() {
    return $this->filename;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function getMimeType() {
    return $this->mimetype;
  }

  public function setMimeType($mimetype) {
    $this->mimetype = $mimetype;
    return $this;
  }

  public function toDictionary() {
    return array(
      'filename' => $this->getFilename(),
      'mimetype' => $this->getMimetype(),
      'data' => $this->getData(),
    );
  }

  public static function newFromDictionary(array $dict) {
    return new PhabricatorMetaMTAAttachment(
      idx($dict, 'data'),
      idx($dict, 'filename'),
      idx($dict, 'mimetype'));
  }

}
