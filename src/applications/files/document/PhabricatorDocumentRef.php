<?php

final class PhabricatorDocumentRef
  extends Phobject {

  private $name;
  private $mimeType;
  private $file;
  private $byteLength;
  private $snippet;
  private $symbolMetadata = array();
  private $blameURI;
  private $coverage = array();

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

  public function getByteLength() {
    if ($this->byteLength !== null) {
      return $this->byteLength;
    }

    if ($this->file) {
      return (int)$this->file->getByteSize();
    }

    return null;
  }

  public function loadData($begin = null, $end = null) {
    if ($this->file) {
      $iterator = $this->file->getFileDataIterator($begin, $end);

      $result = '';
      foreach ($iterator as $chunk) {
        $result .= $chunk;
      }
      return $result;
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

  public function isProbablyText() {
    $snippet = $this->getSnippet();
    return (strpos($snippet, "\0") === false);
  }

  public function isProbablyJSON() {
    if (!$this->isProbablyText()) {
      return false;
    }

    $snippet = $this->getSnippet();
    if (!preg_match('/^\s*[{[]/', $snippet)) {
      return false;
    }

    return phutil_is_utf8($snippet);
  }

  public function getSnippet() {
    if ($this->snippet === null) {
      $this->snippet = $this->loadData(null, (1024 * 1024 * 1));
    }

    return $this->snippet;
  }

  public function setSymbolMetadata(array $metadata) {
    $this->symbolMetadata = $metadata;
    return $this;
  }

  public function getSymbolMetadata() {
    return $this->symbolMetadata;
  }

  public function setBlameURI($blame_uri) {
    $this->blameURI = $blame_uri;
    return $this;
  }

  public function getBlameURI() {
    return $this->blameURI;
  }

  public function addCoverage($coverage) {
    $this->coverage[] = array(
      'data' => $coverage,
    );
    return $this;
  }

  public function getCoverage() {
    return $this->coverage;
  }

}
