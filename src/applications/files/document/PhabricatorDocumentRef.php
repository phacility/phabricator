<?php

final class PhabricatorDocumentRef
  extends Phobject {

  private $name;
  private $mimeType;
  private $file;
  private $byteLength;

  public function setFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    return $this->file;
  }

  public function setMimeType($mime_type) {
    $this->mimeType = $mime_type;
    return $this;
  }

  public function getMimeType() {
    if ($this->mimeType !== null) {
      return $this->mimeType;
    }

    if ($this->file) {
      return $this->file->getMimeType();
    }

    return null;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    if ($this->name !== null) {
      return $this->name;
    }

    if ($this->file) {
      return $this->file->getName();
    }

    return null;
  }

  public function setByteLength($length) {
    $this->byteLength = $length;
    return $this;
  }

  public function getLength() {
    if ($this->byteLength !== null) {
      return $this->byteLength;
    }

    if ($this->file) {
      return (int)$this->file->getByteSize();
    }

    return null;
  }

  public function loadData() {
    if ($this->file) {
      return $this->file->loadFileData();
    }

    throw new PhutilMethodNotImplementedException();
  }

  public function hasAnyMimeType(array $candidate_types) {
    $mime_full = $this->getMimeType();
    $mime_parts = explode(';', $mime_full);

    $mime_type = head($mime_parts);
    $mime_type = $this->normalizeMimeType($mime_type);

    foreach ($candidate_types as $candidate_type) {
      if ($this->normalizeMimeType($candidate_type) === $mime_type) {
        return true;
      }
    }

    return false;
  }

  private function normalizeMimeType($mime_type) {
    $mime_type = trim($mime_type);
    $mime_type = phutil_utf8_strtolower($mime_type);
    return $mime_type;
  }

}
