<?php

final class AphrontMultipartPart extends Phobject {

  private $headers = array();
  private $value = '';

  private $name;
  private $filename;
  private $tempFile;
  private $byteSize = 0;

  public function appendRawHeader($bytes) {
    $parser = id(new AphrontHTTPHeaderParser())
      ->parseRawHeader($bytes);

    $header_name = $parser->getHeaderName();

    $this->headers[] = array(
      $header_name,
      $parser->getHeaderContent(),
    );

    if (strtolower($header_name) === 'content-disposition') {
      $pairs = $parser->getHeaderContentAsPairs();
      foreach ($pairs as $pair) {
        list($key, $value) = $pair;
        switch ($key) {
          case 'filename':
            $this->filename = $value;
            break;
          case 'name':
            $this->name = $value;
            break;
        }
      }
    }

    return $this;
  }

  public function appendData($bytes) {
    $this->byteSize += strlen($bytes);

    if ($this->isVariable()) {
      $this->value .= $bytes;
    } else {
      if (!$this->tempFile) {
        $this->tempFile = new TempFile(getmypid().'.upload');
      }
      Filesystem::appendFile($this->tempFile, $bytes);
    }

    return $this;
  }

  public function isVariable() {
    return ($this->filename === null);
  }

  public function getName() {
    return $this->name;
  }

  public function getVariableValue() {
    if (!$this->isVariable()) {
      throw new Exception(pht('This part is not a variable!'));
    }

    return $this->value;
  }

  public function getPHPFileDictionary() {
    if (!$this->tempFile) {
      $this->appendData('');
    }

    $mime_type = 'application/octet-stream';
    foreach ($this->headers as $header) {
      list($name, $value) = $header;
      if (strtolower($name) == 'content-type') {
        $mime_type = $value;
        break;
      }
    }

    return array(
      'name' => $this->filename,
      'type' => $mime_type,
      'tmp_name' => (string)$this->tempFile,
      'error' => 0,
      'size' => $this->byteSize,
    );
  }

}
