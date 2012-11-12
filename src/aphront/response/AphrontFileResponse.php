<?php

/**
 * @group aphront
 */
final class AphrontFileResponse extends AphrontResponse {

  private $content;
  private $mimeType;
  private $download;

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
    return $this->content;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', $this->getMimeType()),
    );

    if (strlen($this->getDownload())) {
      $headers[] = array('X-Download-Options', 'noopen');

      $filename = $this->getDownload();
      $headers[] = array(
        'Content-Disposition',
        'attachment; filename='.$filename,
      );
    }

    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
