<?php

final class AphrontFileResponse extends AphrontResponse {

  private $content;
  private $contentIterator;
  private $contentLength;

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
    if (!strlen($download)) {
      $download = 'untitled';
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
    $this->setContentLength(strlen($content));
    $this->content = $content;
    return $this;
  }

  public function setContentIterator($iterator) {
    $this->contentIterator = $iterator;
    return $this;
  }

  public function buildResponseString() {
    return $this->content;
  }

  public function getContentIterator() {
    if ($this->contentIterator) {
      return $this->contentIterator;
    }
    return parent::getContentIterator();
  }

  public function setContentLength($length) {
    $this->contentLength = $length;
    return $this;
  }

  public function getContentLength() {
    return $this->contentLength;
  }

  public function setRange($min, $max) {
    $this->rangeMin = $min;
    $this->rangeMax = $max;
    return $this;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', $this->getMimeType()),
      // This tells clients that we can support requests with a "Range" header,
      // which allows downloads to be resumed, in some browsers, some of the
      // time, if the stars align.
      array('Accept-Ranges', 'bytes'),
    );

    if ($this->rangeMin || $this->rangeMax) {
      $len = $this->getContentLength();
      $min = $this->rangeMin;
      $max = $this->rangeMax;
      $headers[] = array('Content-Range', "bytes {$min}-{$max}/{$len}");
      $content_len = ($max - $min) + 1;
    } else {
      $content_len = $this->getContentLength();
    }

    $headers[] = array('Content-Length', $this->getContentLength());

    if (strlen($this->getDownload())) {
      $headers[] = array('X-Download-Options', 'noopen');

      $filename = $this->getDownload();
      $filename = addcslashes($filename, '"\\');
      $headers[] = array(
        'Content-Disposition',
        'attachment; filename="'.$filename.'"',
      );
    }

    if ($this->allowOrigins) {
      $headers[] = array(
        'Access-Control-Allow-Origin',
        implode(',', $this->allowOrigins),
      );
    }

    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
