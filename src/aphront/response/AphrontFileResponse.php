<?php

final class AphrontFileResponse extends AphrontResponse {

  private $content;
  private $mimeType;
  private $download;
  private $rangeMin;
  private $rangeMax;
  private $allowOrigins = array();

  public function addAllowOrigin($origin) {
    $this->allowOrigins[] = $origin;
    return $this;
  }

  public function setDownload($download) {
    $download = preg_replace('/[^A-Za-z0-9_.-]/', '_', $download);
    if (!strlen($download)) {
      $download = 'untitled_document.txt';
    }
    $this->download = $download;
    return $this;
  }

  public function getDownload() {
    return $this->download;
  }

  public function setMimeType($mime_type) {
    $this->mimeType = $mime_type;
    return $this;
  }

  public function getMimeType() {
    return $this->mimeType;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function buildResponseString() {
    if ($this->rangeMin || $this->rangeMax) {
      $length = ($this->rangeMax - $this->rangeMin) + 1;
      return substr($this->content, $this->rangeMin, $length);
    } else {
      return $this->content;
    }
  }

  public function setRange($min, $max) {
    $this->rangeMin = $min;
    $this->rangeMax = $max;
    return $this;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', $this->getMimeType()),
      array('Content-Length', strlen($this->buildResponseString())),
    );

    if ($this->rangeMin || $this->rangeMax) {
      $len = strlen($this->content);
      $min = $this->rangeMin;
      $max = $this->rangeMax;
      $headers[] = array('Content-Range', "bytes {$min}-{$max}/{$len}");
    }

    if (strlen($this->getDownload())) {
      $headers[] = array('X-Download-Options', 'noopen');

      $filename = $this->getDownload();
      $headers[] = array(
        'Content-Disposition',
        'attachment; filename='.$filename,
      );
    }

    if ($this->allowOrigins) {
      $headers[] = array(
        'Access-Control-Allow-Origin',
        implode(',', $this->allowOrigins));
    }

    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
