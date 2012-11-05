<?php

final class PhabricatorMetaMTAAttachment {
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
}
