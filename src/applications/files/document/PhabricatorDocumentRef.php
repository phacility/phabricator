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
  private $data;

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

    if ($this->data !== null) {
      return strlen($this->data);
    }

    if ($this->file) {
      return (int)$this->file->getByteSize();
    }

    return null;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function loadData($begin = null, $end = null) {
    if ($this->data !== null) {
      $data = $this->data;

      if ($begin !== null && $end !== null) {
        $data = substr($data, $begin, $end - $begin);
      } else if ($begin !== null) {
        $data = substr($data, $begin);
      } else if ($end !== null) {
        $data = substr($data, 0, $end);
      }

      return $data;
    }

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

    if (!phutil_nonempty_string($mime_full)) {
      return false;
    }

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

    // If the file is longer than the snippet, we don't detect the content
    // as JSON. We could use some kind of heuristic here if we wanted, but
    // see PHI749 for a false positive.
    if (strlen($snippet) < $this->getByteLength()) {
      return false;
    }

    // If the snippet is the whole file, just check if the snippet is valid
    // JSON. Note that `phutil_json_decode()` only accepts arrays and objects
    // as JSON, so this won't misfire on files with content like "3".
    try {
      phutil_json_decode($snippet);
      return true;
    } catch (Exception $ex) {
      return false;
    }
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
