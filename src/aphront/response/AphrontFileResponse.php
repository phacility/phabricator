<?php

final class AphrontFileResponse extends AphrontResponse {

  private $content;
  private $contentIterator;
  private $contentLength;
  private $compressResponse;

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
    if ($download === null || !strlen($download)) {
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

  public function setCompressResponse($compress_response) {
    $this->compressResponse = $compress_response;
    return $this;
  }

  public function getCompressResponse() {
    return $this->compressResponse;
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

    if ($this->rangeMin !== null || $this->rangeMax !== null) {
      $len = $this->getContentLength();
      $min = $this->rangeMin;

      $max = $this->rangeMax;
      if ($max === null) {
        $max = ($len - 1);
      }

      $headers[] = array('Content-Range', "bytes {$min}-{$max}/{$len}");
      $content_len = ($max - $min) + 1;
    } else {
      $content_len = $this->getContentLength();
    }

    if (!$this->shouldCompressResponse()) {
      $headers[] = array('Content-Length', $content_len);
    }

    $download = $this->getDownload();
    if ($download !== null && strlen($download)) {
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

  protected function shouldCompressResponse() {
    return $this->getCompressResponse();
  }

  public function parseHTTPRange($range) {
    $begin = null;
    $end = null;

    $matches = null;
    if (preg_match('/^bytes=(\d+)-(\d*)$/', $range, $matches)) {
      // Note that the "Range" header specifies bytes differently than
      // we do internally: the range 0-1 has 2 bytes (byte 0 and byte 1).
      $begin = (int)$matches[1];

      // The "Range" may be "200-299" or "200-", meaning "until end of file".
      if ($matches[2] !== null && strlen($matches[2])) {
        $range_end = (int)$matches[2];
        $end = $range_end + 1;
      } else {
        $range_end = null;
      }

      $this->setHTTPResponseCode(206);
      $this->setRange($begin, $range_end);
    }

    return array($begin, $end);
  }

}
